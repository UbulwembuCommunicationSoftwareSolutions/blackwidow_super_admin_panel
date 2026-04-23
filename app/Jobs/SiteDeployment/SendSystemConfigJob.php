<?php

namespace App\Jobs\SiteDeployment;

use App\Jobs\Concerns\AdvancesDeploymentPipeline;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Services\CMSService;
use App\Services\DeploymentStepDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendSystemConfigJob implements ShouldQueue
{
    use AdvancesDeploymentPipeline, Queueable;

    public int $tries = 2;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 30];
    }

    public int $timeout = 120;

    public function __construct(
        public int $customerId,
        public ?int $customerSubscriptionId = null,
        public ?int $deploymentJobId = null
    ) {}

    public function handle(): void
    {
        $customer = Customer::query()->find($this->customerId);
        if (! $customer) {
            Log::warning('site_deployment.send_system_config.missing_customer', [
                'customer_id' => $this->customerId,
            ]);
            if ($this->deploymentJobId !== null) {
                app(DeploymentStepDispatcher::class)->markStepFailed(
                    $this->deploymentJobId,
                    'Customer not found.'
                );
            }

            return;
        }
        $cmsService = new CMSService;
        $console = CustomerSubscription::query()
            ->where('subscription_type_id', 1)
            ->where('customer_id', $customer->id)
            ->first();
        if ($console) {
            Log::info('site_deployment.send_system_config', [
                'customer_id' => $this->customerId,
                'customer_subscription_id' => $console->id,
            ]);
            $cmsService->setConsoleSystemConfigs($console);
        }
        $this->advanceDeploymentPipelineAfterSuccess($this->deploymentJobId);
    }

    public function failed(?Throwable $e): void
    {
        if ($this->deploymentJobId !== null) {
            app(DeploymentStepDispatcher::class)->markStepFailed(
                $this->deploymentJobId,
                $e?->getMessage() ?? 'SendSystemConfigJob failed'
            );
        }
        if ($this->customerSubscriptionId !== null && $this->customerSubscriptionId > 0) {
            $subscription = CustomerSubscription::query()->find($this->customerSubscriptionId);
            if ($subscription) {
                $subscription->last_deployment_error = $e?->getMessage() ?? 'SendSystemConfigJob failed';
                $subscription->last_deployment_error_at = now();
                $subscription->save();
            }
        }
        Log::error('site_deployment.send_system_config_failed', [
            'customer_id' => $this->customerId,
            'message' => $e?->getMessage(),
        ]);
    }
}
