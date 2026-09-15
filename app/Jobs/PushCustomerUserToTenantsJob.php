<?php

namespace App\Jobs;

use App\Models\CustomerUser;
use App\Services\UserSync\TenantUserPusher;
use App\Support\UserSync\PushOperation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Carries a single customer user change out to the tenant apps.
 *
 * Queued so an admin saving a user in Filament never waits on, or is broken by,
 * a tenant being slow or down. Password changes are pushed synchronously by the
 * caller instead, to keep cleartext out of the queue payload.
 */
class PushCustomerUserToTenantsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $customerUserId,
        public PushOperation $operation = PushOperation::Upsert,
    ) {}

    public function handle(TenantUserPusher $pusher): void
    {
        $user = CustomerUser::withTrashed()->find($this->customerUserId);

        if (! $user) {
            Log::warning('Customer user push skipped: record no longer exists', [
                'customer_user_id' => $this->customerUserId,
            ]);

            return;
        }

        match ($this->operation) {
            PushOperation::Upsert => $pusher->upsert($user),
            PushOperation::Archive => $pusher->archive($user),
            PushOperation::Restore => $pusher->restore($user),
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Customer user push to tenants failed permanently', [
            'customer_user_id' => $this->customerUserId,
            'operation' => $this->operation->value,
            'error' => $exception->getMessage(),
        ]);
    }
}
