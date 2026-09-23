<?php

namespace App\Services\UserSync;

use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use App\Models\UserSyncLog;
use App\Support\CustomerSync\LmsHub;
use App\Support\UserSync\TenantResolver;
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
            Log::debug('sync: user push skipped, user_sync disabled', [
                'customer_user_id' => $user->id,
            ]);

            return;
        }

        $subscriptions = $this->targetSubscriptions($user);
        $pushed = [];

        foreach ($subscriptions as $subscription) {
            if (! $this->shouldPushToSubscription($user, $subscription)) {
                continue;
            }

            $this->push($user, rtrim((string) $subscription->url, '/'), $this->tokenFor($subscription), $path, $payload, $extra);
            $pushed[] = TenantResolver::normalise($subscription->url);
        }

        $this->pushConfiguredHub($user, $path, $payload, $extra, $pushed);

        Log::debug('sync: user push dispatch finished', [
            'customer_user_id' => $user->id,
            'path' => $path,
            'lms_access' => (bool) $user->lms_access,
            'is_system_admin' => (bool) $user->is_system_admin,
            'subscription_targets' => $subscriptions->count(),
            'configured_hub_url' => LmsHub::configuredUrl(),
            'already_pushed' => $pushed,
        ]);

        if ($subscriptions->isEmpty() && ! $this->shouldPushToConfiguredHub($user)) {
            Log::info('No tenant apps to push customer user to', [
                'customer_user_id' => $user->id,
                'customer_id' => $user->customer_id,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $extra
     * @param  list<string>  $alreadyPushed
     */
    private function pushConfiguredHub(CustomerUser $user, string $path, array $payload, array $extra, array $alreadyPushed): void
    {
        $hubUrl = LmsHub::configuredUrl();

        if ($hubUrl === null || in_array(TenantResolver::normalise($hubUrl), $alreadyPushed, true)) {
            return;
        }

        if (! $this->shouldPushToConfiguredHub($user)) {
            return;
        }

        $token = (string) (config('services.lms.sync_token') ?: $user->customer?->token ?? '');

        if ($token === '') {
            return;
        }

        $this->push($user, $hubUrl, $token, $path, $payload, $extra);
    }

    private function shouldPushToConfiguredHub(CustomerUser $user): bool
    {
        if (LmsHub::configuredUrl() === null) {
            return false;
        }

        return (bool) $user->lms_access || (bool) $user->is_system_admin;
    }

    private function shouldPushToSubscription(CustomerUser $user, CustomerSubscription $subscription): bool
    {
        if ((int) $subscription->subscription_type_id !== SubscriptionType::LMS_TYPE_ID) {
            return true;
        }

        return (bool) $user->lms_access || (bool) $user->is_system_admin;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $extra
     */
    private function push(
        CustomerUser $user,
        string $baseUrl,
        string $token,
        string $path,
        array $payload,
        array $extra,
    ): void {
        $url = rtrim($baseUrl, '/').'/admin-api/v1/sync/'.$path;

        $body = array_merge([
            'app_url' => $baseUrl,
            'origin' => 'super_admin',
            'user' => $payload,
        ], $extra);

        try {
            $response = $this->client($token)->post($url, $body);
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
            ->filter(fn (CustomerSubscription $subscription) => $this->subscriptionHasAuth($subscription))
            ->values();
    }

    private function subscriptionHasAuth(CustomerSubscription $subscription): bool
    {
        if ((int) $subscription->subscription_type_id === SubscriptionType::LMS_TYPE_ID) {
            return filled(config('services.lms.sync_token'))
                || filled($subscription->customer?->token);
        }

        return filled($subscription->customer?->token);
    }

    private function tokenFor(CustomerSubscription $subscription): string
    {
        $token = (string) ($subscription->customer?->token ?? '');

        if ((int) $subscription->subscription_type_id === SubscriptionType::LMS_TYPE_ID) {
            $token = (string) (config('services.lms.sync_token') ?: $token);
        }

        return $token;
    }

    private function client(string $token): PendingRequest
    {
        return Http::withToken($token)
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
