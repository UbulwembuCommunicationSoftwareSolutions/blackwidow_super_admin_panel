<?php

namespace App\Services\UserSync;

use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\UserSyncLog;
use App\Support\UserSync\UserSyncPayload;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pushes a single customer user to the tenant apps that speak the canonical
 * contract, rather than asking them to re-import everyone.
 *
 * The tenant replies with its own local user id, which is how this panel learns
 * the other half of the two-way identity link for users it created itself.
 */
class TenantUserPusher
{
    public function upsert(CustomerUser $user): void
    {
        $this->dispatchToTenants($user, 'users', UserSyncPayload::fromCustomerUser($user)->toArray());
    }

    public function archive(CustomerUser $user): void
    {
        $this->dispatchToTenants($user, 'users/archive', UserSyncPayload::fromCustomerUser($user)->toArray());
    }

    public function restore(CustomerUser $user): void
    {
        $this->dispatchToTenants($user, 'users/restore', UserSyncPayload::fromCustomerUser($user)->toArray());
    }

    /**
     * @param  string  $password  Cleartext; the tenant hashes it with its own driver.
     */
    public function password(CustomerUser $user, string $password): void
    {
        $this->dispatchToTenants(
            $user,
            'users/password',
            UserSyncPayload::fromCustomerUser($user)->toArray(),
            ['password' => $password],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $extra
     */
    private function dispatchToTenants(CustomerUser $user, string $path, array $payload, array $extra = []): void
    {
        if (! config('user_sync.enabled', true)) {
            return;
        }

        $subscriptions = $this->targetSubscriptions($user);

        if ($subscriptions->isEmpty()) {
            Log::info('No tenant apps to push customer user to', [
                'customer_user_id' => $user->id,
                'customer_id' => $user->customer_id,
            ]);

            return;
        }

        foreach ($subscriptions as $subscription) {
            $this->push($user, $subscription, $path, $payload, $extra);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $extra
     */
    private function push(
        CustomerUser $user,
        CustomerSubscription $subscription,
        string $path,
        array $payload,
        array $extra,
    ): void {
        $url = rtrim((string) $subscription->url, '/').'/admin-api/v1/sync/'.$path;

        $body = array_merge([
            'app_url' => $subscription->url,
            'origin' => 'super_admin',
            'user' => $payload,
        ], $extra);

        try {
            $response = $this->client($subscription)->post($url, $body);
        } catch (\Throwable $e) {
            $this->log($user, 'failed', $e->getMessage(), ['url' => $url]);

            Log::error('Customer user push to tenant threw', [
                'customer_user_id' => $user->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        if (! $response->successful()) {
            $this->log($user, 'failed', 'HTTP '.$response->status().' '.$response->body(), ['url' => $url]);

            Log::error('Customer user push to tenant failed', [
                'customer_user_id' => $user->id,
                'url' => $url,
                'status' => $response->status(),
            ]);

            throw new \RuntimeException('Tenant user sync failed with HTTP '.$response->status().' for '.$url);
        }

        $this->rememberTenantUserId($user, $response->json('user.cms_user_id'));

        $user->forceFill([
            'last_synced_at' => now(),
            'sync_hash' => $this->syncHash($user),
        ])->saveQuietly();

        $this->log($user, 'success', null, ['url' => $url, 'response' => $response->json()]);
    }

    /**
     * @return Collection<int, CustomerSubscription>
     */
    private function targetSubscriptions(CustomerUser $user): Collection
    {
        return CustomerSubscription::query()
            ->where('customer_id', $user->customer_id)
            ->whereIn('subscription_type_id', (array) config('user_sync.tenant_subscription_types', [1]))
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->with('customer')
            ->get()
            ->filter(fn (CustomerSubscription $subscription) => filled($subscription->customer?->token))
            ->values();
    }

    private function client(CustomerSubscription $subscription): PendingRequest
    {
        return Http::withToken((string) $subscription->customer->token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('user_sync.timeout', 15))
            ->connectTimeout(10);
    }

    private function rememberTenantUserId(CustomerUser $user, mixed $tenantUserId): void
    {
        if (blank($tenantUserId) || (int) $tenantUserId === (int) $user->cms_user_id) {
            return;
        }

        $user->forceFill(['cms_user_id' => (int) $tenantUserId])->saveQuietly();
    }

    /**
     * Fingerprint of the fields that travel over the wire, so a later change can
     * be told apart from a replay of one we already pushed.
     */
    public function syncHash(CustomerUser $user): string
    {
        $data = UserSyncPayload::fromCustomerUser($user)->toArray();

        unset($data['updated_at']);
        ksort($data);

        return hash('sha256', (string) json_encode($data));
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function log(CustomerUser $user, string $status, ?string $error = null, ?array $data = null): void
    {
        UserSyncLog::create([
            'customer_user_id' => $user->id,
            'direction' => 'outbound',
            'status' => $status,
            'error_message' => $error,
            'sync_data' => $data,
            'synced_at' => now(),
        ]);
    }
}
