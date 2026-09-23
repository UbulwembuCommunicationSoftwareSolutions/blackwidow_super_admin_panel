<?php

namespace App\Jobs;

use App\Models\CustomerUser;
use App\Services\UserFieldSync\TenantUserFieldPusher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PushCustomerUserFieldValuesToTenantsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $customerUserId) {}

    public function handle(TenantUserFieldPusher $pusher): void
    {
        $user = CustomerUser::query()->find($this->customerUserId);
        if ($user === null) {
            Log::warning('User field values push skipped: user no longer exists', [
                'customer_user_id' => $this->customerUserId,
            ]);

            return;
        }

        $pusher->pushValuesForUser($user);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('User field values push to tenants failed permanently', [
            'customer_user_id' => $this->customerUserId,
            'error' => $exception->getMessage(),
        ]);
    }
}
