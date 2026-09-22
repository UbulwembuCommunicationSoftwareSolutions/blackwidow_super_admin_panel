<?php

namespace App\Jobs\SiteDeployment;

use App\Jobs\Concerns\AdvancesDeploymentPipeline;
use App\Jobs\Concerns\LogsSiteDeploymentFailure;
use App\Models\CustomerSubscription;
use App\Services\DeploymentScriptRenderer;
use App\Services\DeploymentStepDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AddDeploymentScriptOnForgeJob implements ShouldQueue
{
    use AdvancesDeploymentPipeline, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use LogsSiteDeploymentFailure;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [20, 40, 60];
    }

    public int $timeout = 300;

    public function __construct(
        public int $customerSubscriptionId,
        public ?int $deploymentJobId = null
    ) {}

    public function handle(DeploymentScriptRenderer $renderer): void
    {
        $customerSubscription = CustomerSubscription::query()->find($this->customerSubscriptionId);
        if (! $customerSubscription) {
            Log::warning('site_deployment.add_deploy_script.missing_subscription', [
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

        Log::info('site_deployment.add_deployment_script', [
            'customer_subscription_id' => $this->customerSubscriptionId,
        ]);

        [$script, $pushed] = $renderer->renderAndPush($customerSubscription, pushToForge: true);

        Log::info('site_deployment.add_deployment_script.done', [
            'customer_subscription_id' => $this->customerSubscriptionId,
            'rendered_release_id' => $script->rendered_release_id,
            'pushed' => $pushed,
        ]);

        $this->advanceDeploymentPipelineAfterSuccess($this->deploymentJobId);
    }
}
