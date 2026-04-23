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

class AddSSLOnSiteJob implements ShouldQueue
{
    use AdvancesDeploymentPipeline, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use LogsSiteDeploymentFailure;

    /**
     * Do not repeat LE order attempts: Forge wait=false is the main success path; retries could hit LE rate limits.
     */
    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $customerSubscriptionId,
        public ?int $deploymentJobId = null
    ) {}

    public function handle(): void
    {
        $customerSubscription = CustomerSubscription::query()->find($this->customerSubscriptionId);
        if (! $customerSubscription) {
            Log::warning('site_deployment.add_ssl.missing_subscription', [
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

        Log::info('site_deployment.add_ssl', [
            'customer_subscription_id' => $this->customerSubscriptionId,
            'note' => 'LE order accepted on success; cert may still be installing on Forge.',
        ]);
        $forgeApi = new ForgeApi;
        $forgeApi->letsEncryptCertificate($customerSubscription);
        $this->advanceDeploymentPipelineAfterSuccess($this->deploymentJobId);
    }
}
