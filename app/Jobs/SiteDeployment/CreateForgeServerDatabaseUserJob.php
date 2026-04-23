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

class CreateForgeServerDatabaseUserJob implements ShouldQueue
{
    use AdvancesDeploymentPipeline, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use LogsSiteDeploymentFailure;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [20, 60, 120];
    }

    public int $timeout = 300;

    public function __construct(
        public int $customerSubscriptionId,
        public ?int $deploymentJobId = null
    ) {}

    public function handle(): void
    {
        Log::info('site_deployment.create_forge_server_database_user', [
            'customer_subscription_id' => $this->customerSubscriptionId,
        ]);
        $customerSubscription = CustomerSubscription::query()->find($this->customerSubscriptionId);
        if (! $customerSubscription) {
            Log::warning('site_deployment.create_forge_server_database_user.missing_subscription', [
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
        if (! (new ForgeApi)->needsForgeServerDatabase($customerSubscription)) {
            $this->advanceDeploymentPipelineAfterSuccess($this->deploymentJobId);

            return;
        }
        if (! $customerSubscription->server_id) {
            $message = 'server_id is required to create a Forge MySQL database user.';
            if ($this->deploymentJobId !== null) {
                app(DeploymentStepDispatcher::class)->markStepFailed(
                    $this->deploymentJobId,
                    $message
                );
            }
            throw new \RuntimeException($message);
        }
        (new ForgeApi)->provisionForgeServerDatabaseUser($customerSubscription->server_id, $customerSubscription);
        $this->advanceDeploymentPipelineAfterSuccess($this->deploymentJobId);
    }
}
