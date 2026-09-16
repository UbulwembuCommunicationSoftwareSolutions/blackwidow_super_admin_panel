<?php

namespace App\Jobs\SiteDeployment;

use App\Helpers\ForgeApi;
use App\Jobs\Concerns\AdvancesDeploymentPipeline;
use App\Jobs\Concerns\LogsSiteDeploymentFailure;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionDeploymentJob;
use App\Services\DeploymentStepDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateSiteOnForgeJob implements ShouldQueue
{
    use AdvancesDeploymentPipeline, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use LogsSiteDeploymentFailure;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public int $timeout = 300;

    public function __construct(
        public int $customerSubscriptionId,
        public ?int $deploymentJobId = null
    ) {}

    public function handle(): void
    {
        Log::info('site_deployment.create_site_job', [
            'customer_subscription_id' => $this->customerSubscriptionId,
        ]);
        $customerSubscription = CustomerSubscription::query()->find($this->customerSubscriptionId);
        if (! $customerSubscription) {
            Log::warning('site_deployment.create_site.missing_subscription', [
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
        $skipDatabaseProvisioning = false;
        if ($this->deploymentJobId !== null) {
            $deploymentRow = CustomerSubscriptionDeploymentJob::query()->find($this->deploymentJobId);
            if ($deploymentRow && ! empty($deploymentRow->parameters['skip_database_provision'])) {
                $skipDatabaseProvisioning = (bool) $deploymentRow->parameters['skip_database_provision'];
            }
        }
        $forgeApi = new ForgeApi;
        $forgeApi->createSite($customerSubscription->server_id, $customerSubscription, $skipDatabaseProvisioning);
        $customerSubscription->refresh();
        if (blank($customerSubscription->forge_site_id)) {
            $message = 'forge_site_id was not set after creating the site on Forge; deployment will not continue.';
            Log::error('site_deployment.create_site.missing_forge_site_id', [
                'customer_subscription_id' => $this->customerSubscriptionId,
            ]);
            if ($this->deploymentJobId !== null) {
                app(DeploymentStepDispatcher::class)->markStepFailed(
                    $this->deploymentJobId,
                    $message
                );
            }
            throw new \RuntimeException($message);
        }

        if ($this->deploymentJobId !== null) {
            $forgeStatus = $forgeApi->waitForSiteStatus(
                (int) $customerSubscription->server_id,
                (int) $customerSubscription->forge_site_id,
                ['installed', 'failed'],
                90
            );
            app(DeploymentStepDispatcher::class)->recordForgeStatus($this->deploymentJobId, $forgeStatus);
            if ($forgeStatus === 'failed') {
                app(DeploymentStepDispatcher::class)->markStepFailed(
                    $this->deploymentJobId,
                    'Forge reported the site installation failed.'
                );

                return;
            }
        }

        $this->advanceDeploymentPipelineAfterSuccess($this->deploymentJobId);
    }
}
