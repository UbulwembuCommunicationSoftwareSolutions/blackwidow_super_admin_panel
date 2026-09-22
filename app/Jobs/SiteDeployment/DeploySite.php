<?php

namespace App\Jobs\SiteDeployment;

use App\Helpers\ForgeApi;
use App\Jobs\Concerns\AdvancesDeploymentPipeline;
use App\Jobs\Concerns\LogsSiteDeploymentFailure;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionDeploymentJob;
use App\Models\SubscriptionTypeRelease;
use App\Services\DeploymentStepDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Laravel\Forge\Resources\Deployment;

class DeploySite implements ShouldQueue
{
    use AdvancesDeploymentPipeline, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use LogsSiteDeploymentFailure;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 90];
    }

    public int $timeout = 300;

    public function __construct(
        public int $customerSubscriptionId,
        public ?int $deploymentJobId = null
    ) {}

    public function handle(): void
    {
        $customerSubscription = CustomerSubscription::query()
            ->with(['subscriptionType.currentRelease', 'pinnedRelease'])
            ->find($this->customerSubscriptionId);
        if (! $customerSubscription) {
            Log::warning('site_deployment.deploy_site.missing_subscription', [
                'customer_subscription_id' => $this->customerSubscriptionId,
            ]);
            if ($this->deploymentJobId !== null) {
                app(DeploymentStepDispatcher::class)->markStepFailed(
                    $this->deploymentJobId,
                    'Customer subscription not found.'
                );
            }

            return;
        }

        $target = $customerSubscription->targetRelease();
        $this->recordIntendedRelease($target);

        $forgeApi = app(ForgeApi::class);
        $customerSubscription = $forgeApi->assertForgeSiteReady($customerSubscription);
        Log::info('site_deployment.deploy_site', [
            'customer_subscription_id' => $this->customerSubscriptionId,
            'release_id' => $target?->id,
            'release_tag' => $target?->tag,
        ]);

        $deployment = $forgeApi->deploySite($customerSubscription->server_id, $customerSubscription->forge_site_id);

        if ($this->deploymentJobId !== null) {
            $serverId = (int) $customerSubscription->server_id;
            $siteId = (int) $customerSubscription->forge_site_id;
            $finished = $forgeApi->waitForDeployment($serverId, $siteId, (int) $deployment->id, 240);
            $forgeStatus = $finished->status ?? 'pending';

            $failed = in_array($forgeStatus, ['failed', 'failed-build', 'cancelled'], true);
            $forgeLog = $forgeApi->deploymentLog($serverId, $siteId, (int) $deployment->id);
            app(DeploymentStepDispatcher::class)->recordForgeStatus($this->deploymentJobId, $forgeStatus, $forgeLog);

            if ($failed) {
                app(DeploymentStepDispatcher::class)->markStepFailed(
                    $this->deploymentJobId,
                    'Forge deployment '.$forgeStatus.'.'.($forgeLog ? ' See the deployment log for details.' : '')
                );

                return;
            }

            if ($forgeStatus === 'finished') {
                $this->confirmDeployedRelease(
                    $customerSubscription,
                    $forgeApi,
                    $finished,
                    $forgeLog,
                    $target
                );
            }
        } else {
            // Outside the pipeline we cannot wait/confirm; leave deployed_* untouched.
            $customerSubscription->forceFill(['deployed_at' => now()])->save();
        }

        $this->advanceDeploymentPipelineAfterSuccess($this->deploymentJobId);
    }

    private function recordIntendedRelease(?SubscriptionTypeRelease $target): void
    {
        if ($this->deploymentJobId === null || $target === null) {
            return;
        }

        $row = CustomerSubscriptionDeploymentJob::query()->find($this->deploymentJobId);
        if (! $row) {
            return;
        }

        $params = $row->parameters ?? [];
        $params['release_id'] = $target->id;
        $params['release_tag'] = $target->tag;
        $row->forceFill(['parameters' => $params])->save();
    }

    private function confirmDeployedRelease(
        CustomerSubscription $customerSubscription,
        ForgeApi $forgeApi,
        Deployment $deployment,
        string $forgeLog,
        ?SubscriptionTypeRelease $intended
    ): void {
        $marker = $forgeApi->parseDeployedReleaseMarker($forgeLog);
        $tag = $marker['tag'] ?? $intended?->tag;
        $commitSha = $marker['commit_sha']
            ?? $forgeApi->deploymentCommitSha($deployment);

        $release = null;
        if ($commitSha) {
            $release = SubscriptionTypeRelease::query()
                ->where('subscription_type_id', $customerSubscription->subscription_type_id)
                ->where('commit_sha', $commitSha)
                ->first();
        }
        if (! $release && $tag) {
            $release = SubscriptionTypeRelease::query()
                ->where('subscription_type_id', $customerSubscription->subscription_type_id)
                ->where('tag', $tag)
                ->first();
        }

        $attributes = [
            'deployed_at' => now(),
            'deployed_confirmed_at' => now(),
            'deployed_tag_raw' => $tag,
            'deployed_commit_sha' => $commitSha,
            'deployed_release_id' => $release?->id,
        ];

        if ($release) {
            $attributes['deployed_version'] = $release->tag;
        } elseif ($tag) {
            $attributes['deployed_version'] = $tag;
        }

        // Idempotent: same confirmed release on a second DEPLOY_SITE in the same pipeline is a no-op.
        if (
            (int) $customerSubscription->deployed_release_id === (int) ($release?->id)
            && $customerSubscription->deployed_commit_sha === $commitSha
            && $customerSubscription->deployed_confirmed_at !== null
        ) {
            $customerSubscription->forceFill([
                'deployed_at' => $customerSubscription->deployed_at ?? now(),
            ])->save();

            return;
        }

        $customerSubscription->forceFill($attributes)->save();

        Log::info('site_deployment.deploy_site.confirmed', [
            'customer_subscription_id' => $customerSubscription->id,
            'deployed_release_id' => $release?->id,
            'deployed_tag_raw' => $tag,
            'deployed_commit_sha' => $commitSha,
        ]);
    }
}
