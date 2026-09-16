<?php

namespace App\Services\BrandingSync;

use App\Models\BrandingSyncLog;
use App\Models\CustomerSubscription;
use App\Support\BrandingSync\BrandingSyncPayload;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pushes a single branding slot to tenant CMS apps that speak the canonical contract.
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
                ! in_array((int) $subscription->subscription_type_id, array_map('intval', (array) config('branding_sync.tenant_subscription_types', [1])), true) => 'subscription_type_id not a tenant type',
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
                $this->push($subscription, $target, $payload);
            }
        }
    }

    private function push(
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
            $response = $this->client($target)->post($url, $body);
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

    /**
     * @return Collection<int, CustomerSubscription>
     */
    private function targetSubscriptions(CustomerSubscription $subscription): Collection
    {
        // Push to the same subscription's URL when it is a CMS tenant.
        $types = (array) config('branding_sync.tenant_subscription_types', [1]);

        if (
            in_array((int) $subscription->subscription_type_id, array_map('intval', $types), true)
            && filled($subscription->url)
        ) {
            $subscription->loadMissing('customer');
            if (filled($subscription->customer?->token)) {
                return collect([$subscription]);
            }
        }

        return collect();
    }

    private function client(CustomerSubscription $subscription): PendingRequest
    {
        $subscription->loadMissing('customer');

        return Http::withToken((string) $subscription->customer->token)
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
