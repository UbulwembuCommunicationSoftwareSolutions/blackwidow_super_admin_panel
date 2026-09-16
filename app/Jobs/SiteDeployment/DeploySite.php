<?php

namespace App\Jobs\SiteDeployment;

use App\Helpers\ForgeApi;
use App\Jobs\Concerns\AdvancesDeploymentPipeline;
use App\Jobs\Concerns\LogsSiteDeploymentFailure;
use App\Models\CustomerSubscription;
use App\Services\DeploymentStepDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
            ->with('subscriptionType')
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

        $forgeApi = new ForgeApi;
        $customerSubscription = $forgeApi->assertForgeSiteReady($customerSubscription);
        Log::info('site_deployment.deploy_site', [
            'customer_subscription_id' => $this->customerSubscriptionId,
        ]);
        $customerSubscription->deployed_at = now();
        $customerSubscription->deployed_version = $customerSubscription->subscriptionType?->master_version;
        $customerSubscription->save();
        $deployment = $forgeApi->deploySite($customerSubscription->server_id, $customerSubscription->forge_site_id);

        if ($this->deploymentJobId !== null) {
            $serverId = (int) $customerSubscription->server_id;
            $siteId = (int) $customerSubscription->forge_site_id;
            $forgeStatus = $forgeApi->waitForDeploymentStatus($serverId, $siteId, (int) $deployment->id, 240);

            $failed = in_array($forgeStatus, ['failed', 'failed-build', 'cancelled'], true);
            $forgeLog = $failed ? $forgeApi->deploymentLog($serverId, $siteId, (int) $deployment->id) : null;
            app(DeploymentStepDispatcher::class)->recordForgeStatus($this->deploymentJobId, $forgeStatus, $forgeLog);

            if ($failed) {
                app(DeploymentStepDispatcher::class)->markStepFailed(
                    $this->deploymentJobId,
                    'Forge deployment ' . $forgeStatus . '.' . ($forgeLog ? ' See the deployment log for details.' : '')
                );

                return;
            }
        }

        $this->advanceDeploymentPipelineAfterSuccess($this->deploymentJobId);
    }
}
