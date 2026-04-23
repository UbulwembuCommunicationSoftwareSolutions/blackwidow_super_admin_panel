<?php

namespace App\Jobs\SiteDeployment;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Services\CMSService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendSystemConfigJob implements ShouldQueue
{
    use Queueable;

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
        public int $customerId
    ) {}

    public function handle(): void
    {
        $customer = Customer::query()->find($this->customerId);
        if (! $customer) {
            Log::warning('site_deployment.send_system_config.missing_customer', [
                'customer_id' => $this->customerId,
            ]);

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
    }

    public function failed(?Throwable $e): void
    {
        Log::error('site_deployment.send_system_config_failed', [
            'customer_id' => $this->customerId,
            'message' => $e?->getMessage(),
        ]);
    }
}
