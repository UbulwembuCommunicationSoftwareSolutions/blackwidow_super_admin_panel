<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\CustomerSync\TenantCustomerPusher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PushCustomerToTenantsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $customerId) {}

    public function handle(TenantCustomerPusher $pusher): void
    {
        $customer = Customer::query()->find($this->customerId);

        if (! $customer) {
            Log::warning('Customer push skipped: record no longer exists', [
                'customer_id' => $this->customerId,
            ]);

            return;
        }

        $pusher->upsert($customer);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Customer push to LMS tenants failed permanently', [
            'customer_id' => $this->customerId,
            'error' => $exception->getMessage(),
        ]);
    }
}
