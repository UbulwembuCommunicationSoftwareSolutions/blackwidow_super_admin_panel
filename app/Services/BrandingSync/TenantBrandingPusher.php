<?php

namespace App\Services\BrandingSync;

use App\Models\BrandingSyncLog;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Support\BrandingSync\BrandingSyncPayload;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pushes branding slots to tenant apps (CMS, Firearm) and the shared LMS hub.
 */
class TenantBrandingPusher
{
    /**
     * @param  list<string>  $cmsSlots
     */
    public function pushSlots(CustomerSubscription $subscription, array $cmsSlots): void
    {
        if (! config('branding_sync.enabled', true)) {
            Log::info('Branding push skipped: branding_sync disabled', [
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        $targets = $this->targetSubscriptions($subscription);
        if ($targets->isEmpty()) {
            $subscription->loadMissing('customer');
            $reason = match (true) {
                ! in_array((int) $subscription->subscription_type_id, $this->tenantTypeIds(), true) => 'subscription_type_id not a tenant type',
                blank($subscription->url) => 'subscription has no url',
                blank($subscription->customer?->token) => 'customer has no token',
                default => 'unknown',
            };

            Log::info('No tenant apps to push branding to', [
                'subscription_id' => $subscription->id,
                'subscription_type_id' => $subscription->subscription_type_id,
                'url' => $subscription->url,
                'reason' => $reason,
            ]);

            foreach ($cmsSlots as $cmsSlot) {
                $this->log($subscription, $cmsSlot, 'skipped', $reason);
            }

            return;
        }

        foreach ($cmsSlots as $cmsSlot) {
            if (! in_array($cmsSlot, BrandingSyncPayload::SLOTS, true)) {
                continue;
            }

            $payload = BrandingSyncPayload::fromSubscription($subscription, $cmsSlot);

            foreach ($targets as $target) {
                $this->pushToTenant($subscription, $target, $payload);
            }
        }
    }

    /**
     * @param  list<string>  $cmsSlots
     */
    public function pushCustomerDefaultsToTenants(Customer $customer, array $cmsSlots): void
    {
        if (! config('branding_sync.enabled', true)) {
            return;
        }

        $subscriptions = CustomerSubscription::query()
            ->where('customer_id', $customer->id)
            ->whereIn('subscription_type_id', $this->tenantTypeIds())
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->get();

        foreach ($subscriptions as $subscription) {
            $this->pushSlots($subscription, $cmsSlots);
        }
    }

    /**
     * @param  list<string>  $cmsSlots
     */
    public function pushCustomerDefaultsToLmsHub(Customer $customer, array $cmsSlots): void
    {
        if (! config('branding_sync.enabled', true)) {
            return;
        }

        if (! config('branding_sync.lms_hub_enabled', true)) {
            return;
        }

        $targets = $this->lmsSubscriptions($customer);
        if ($targets->isEmpty()) {
            Log::info('No LMS hub to push customer branding to', [
                'customer_id' => $customer->id,
            ]);

            return;
        }

        foreach ($cmsSlots as $cmsSlot) {
            if (! in_array($cmsSlot, BrandingSyncPayload::SLOTS, true)) {
                continue;
            }

            $payload = BrandingSyncPayload::fromCustomerDefault($customer, $cmsSlot);

            foreach ($targets as $target) {
                $this->pushToLmsHub($customer, $target, $payload);
            }
        }
    }

    private function pushToTenant(
        CustomerSubscription $source,
        CustomerSubscription $target,
        BrandingSyncPayload $payload,
    ): void {
        $url = rtrim((string) $target->url, '/').'/admin-api/v1/sync/branding';

        $body = [
            'app_url' => $target->url,
            'origin' => 'super_admin',
            'branding' => $payload->toArray(),
        ];

        try {
            $response = $this->tenantClient($target)->post($url, $body);
        } catch (\Throwable $e) {
            $this->log($source, $payload->slot, 'failed', $e->getMessage(), [
                'url' => $url,
                'request' => $body,
            ]);

            Log::error('Branding push to tenant threw', [
                'subscription_id' => $source->id,
                'slot' => $payload->slot,
                'url' => $url,
                'request' => $body,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        if (! $response->successful()) {
            $this->log($source, $payload->slot, 'failed', 'HTTP '.$response->status().' '.$response->body(), [
                'url' => $url,
                'request' => $body,
                'status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            Log::error('Branding push to tenant failed', [
                'subscription_id' => $source->id,
                'slot' => $payload->slot,
                'url' => $url,
                'request' => $body,
                'status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            throw new \RuntimeException('Tenant branding sync failed with HTTP '.$response->status().' for '.$url);
        }

        $this->log($source, $payload->slot, 'success', null, [
            'url' => $url,
            'request' => $body,
            'outcome' => $response->json('outcome'),
            'response' => $response->json(),
        ]);

        Log::info('Branding push to tenant succeeded', [
            'subscription_id' => $source->id,
            'slot' => $payload->slot,
            'url' => $url,
            'request' => $body,
            'outcome' => $response->json('outcome'),
            'response' => $response->json(),
        ]);
    }

    private function pushToLmsHub(
        Customer $customer,
        CustomerSubscription $lmsSubscription,
        BrandingSyncPayload $payload,
    ): void {
        $url = rtrim((string) $lmsSubscription->url, '/').'/admin-api/v1/sync/branding';

        $body = [
            'app_url' => $lmsSubscription->url,
            'origin' => 'super_admin',
            'branding' => $payload->toLmsArray($customer->id),
        ];

        try {
            $response = $this->lmsClient()->post($url, $body);
        } catch (\Throwable $e) {
            $this->log($lmsSubscription, $payload->slot, 'failed', $e->getMessage(), [
                'url' => $url,
                'request' => $body,
                'target' => 'lms',
            ]);

            Log::error('Branding push to LMS hub threw', [
                'customer_id' => $customer->id,
                'slot' => $payload->slot,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        if (! $response->successful()) {
            $this->log($lmsSubscription, $payload->slot, 'failed', 'HTTP '.$response->status().' '.$response->body(), [
                'url' => $url,
                'request' => $body,
                'target' => 'lms',
                'status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            throw new \RuntimeException('LMS branding sync failed with HTTP '.$response->status().' for '.$url);
        }

        $this->log($lmsSubscription, $payload->slot, 'success', null, [
            'url' => $url,
            'request' => $body,
            'target' => 'lms',
            'response' => $response->json(),
        ]);
    }

    /**
     * @return Collection<int, CustomerSubscription>
     */
    private function targetSubscriptions(CustomerSubscription $subscription): Collection
    {
        if (
            in_array((int) $subscription->subscription_type_id, $this->tenantTypeIds(), true)
            && filled($subscription->url)
        ) {
            $subscription->loadMissing('customer');
            if (filled($subscription->customer?->token)) {
                return collect([$subscription]);
            }
        }

        return collect();
    }

    /**
     * @return Collection<int, CustomerSubscription>
     */
    private function lmsSubscriptions(Customer $customer): Collection
    {
        $typeId = (int) config('branding_sync.lms_subscription_type_id');

        return CustomerSubscription::query()
            ->where('customer_id', $customer->id)
            ->where('subscription_type_id', $typeId)
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->get()
            ->values();
    }

    /**
     * @return list<int>
     */
    private function tenantTypeIds(): array
    {
        return array_map('intval', (array) config('branding_sync.tenant_subscription_types', [1]));
    }

    private function tenantClient(CustomerSubscription $subscription): PendingRequest
    {
        $subscription->loadMissing('customer');

        return Http::withToken((string) $subscription->customer->token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('branding_sync.timeout', 30))
            ->connectTimeout(10);
    }

    private function lmsClient(): PendingRequest
    {
        $token = config('services.lms.sync_token');
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('LMS sync token is not configured (services.lms.sync_token).');
        }

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('branding_sync.timeout', 30))
            ->connectTimeout(10);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function log(
        CustomerSubscription $subscription,
        string $slot,
        string $status,
        ?string $error = null,
        ?array $data = null,
    ): void {
        BrandingSyncLog::create([
            'customer_subscription_id' => $subscription->id,
            'slot' => $slot,
            'direction' => 'outbound',
            'status' => $status,
            'error_message' => $error,
            'sync_data' => $data,
            'synced_at' => now(),
        ]);
    }
}
