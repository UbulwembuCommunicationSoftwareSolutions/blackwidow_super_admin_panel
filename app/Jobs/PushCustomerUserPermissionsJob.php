<?php

namespace App\Jobs;

use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Services\UserSync\TenantUserPusher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Carries one user's granular permissions for one subscription out to that
 * subscription's tenant app.
 */
class PushCustomerUserPermissionsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $customerUserId,
        public int $customerSubscriptionId,
    ) {}

    public function handle(TenantUserPusher $pusher): void
    {
        $user = CustomerUser::withTrashed()->find($this->customerUserId);
        $subscription = CustomerSubscription::query()->find($this->customerSubscriptionId);

        if (! $user || ! $subscription) {
            Log::warning('Customer user permission push skipped: record no longer exists', [
                'customer_user_id' => $this->customerUserId,
                'customer_subscription_id' => $this->customerSubscriptionId,
            ]);

            return;
        }

        $pusher->permissions($user, $subscription);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Customer user permission push failed permanently', [
            'customer_user_id' => $this->customerUserId,
            'customer_subscription_id' => $this->customerSubscriptionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
