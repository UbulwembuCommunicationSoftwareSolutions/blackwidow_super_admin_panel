<?php

namespace App\Jobs;

use App\Models\CustomerUser;
use App\Services\UserSync\TenantUserPusher;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Carries a password changed in this panel out to the tenant apps.
 *
 * A tenant only learns a new password from this push (the profile upsert never
 * carries one), so a push that is lost leaves the previous password valid on that
 * tenant until the user next signs in with the new one. Queued with a long retry
 * tail so a tenant that is down for hours still catches up, and encrypted because
 * the payload holds the cleartext the tenant hashes with its own driver.
 */
class PushCustomerUserPasswordJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900, 3600, 10800];

    public function __construct(
        public int $customerUserId,
        public string $password,
    ) {}

    public function handle(TenantUserPusher $pusher): void
    {
        $user = CustomerUser::find($this->customerUserId);

        if (! $user) {
            Log::warning('Customer user password push skipped: record no longer exists', [
                'customer_user_id' => $this->customerUserId,
            ]);

            return;
        }

        // A later change supersedes this one; pushing the older password after the
        // newer one would put the stale credential back on the tenant.
        if (! Hash::check($this->password, (string) $user->password)) {
            Log::info('Customer user password push skipped: superseded by a newer change', [
                'customer_user_id' => $this->customerUserId,
            ]);

            return;
        }

        $pusher->password($user, $this->password);
    }

    public function failed(\Throwable $exception): void
    {
        // Never log the password itself.
        Log::error('Customer user password push to tenants failed permanently', [
            'customer_user_id' => $this->customerUserId,
            'error' => $exception->getMessage(),
        ]);
    }
}
