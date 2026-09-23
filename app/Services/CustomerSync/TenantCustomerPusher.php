<?php

namespace App\Services\CustomerSync;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Support\CustomerSync\CustomerSyncPayload;
use App\Support\CustomerSync\LmsHub;
use App\Support\UserSync\TenantResolver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TenantCustomerPusher
{
    public function upsert(Customer $customer): void
    {
        if (! config('customer_sync.enabled', true)) {
            Log::debug('sync: customer push skipped, customer_sync disabled', [
                'customer_id' => $customer->id,
            ]);

            return;
        }

        $targets = $this->targetUrls($customer);

        Log::debug('sync: customer push targets', [
            'customer_id' => $customer->id,
            'targets' => $targets,
            'configured_hub_url' => LmsHub::configuredUrl(),
        ]);

        if ($targets === []) {
            Log::info('No LMS tenants to push customer to', [
                'customer_id' => $customer->id,
            ]);

            return;
        }

        $payload = CustomerSyncPayload::fromCustomer($customer);

        foreach ($targets as $hubUrl) {
            $this->push($customer, $hubUrl, $payload);
        }
    }

    private function push(
        Customer $customer,
        string $hubUrl,
        CustomerSyncPayload $payload,
    ): void {
        $url = rtrim($hubUrl, '/').'/admin-api/v1/sync/customers';

        $body = array_merge($payload->toArray(), [
            'app_url' => $hubUrl,
            'origin' => 'super_admin',
        ]);

        try {
            $response = $this->client($customer)->post($url, $body);
        } catch (\Throwable $e) {
            Log::error('Customer push to LMS tenant threw', [
                'customer_id' => $customer->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        if (! $response->successful()) {
            Log::error('Customer push to LMS tenant failed', [
                'customer_id' => $customer->id,
                'url' => $url,
                'status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            throw new \RuntimeException('LMS customer sync failed with HTTP '.$response->status().' for '.$url);
        }

        Log::info('Customer push to LMS tenant succeeded', [
            'customer_id' => $customer->id,
            'url' => $url,
            'response' => $response->json(),
        ]);
    }

    /**
     * Configured shared hub first, then any legacy per-customer LMS subscription URLs.
     *
     * @return list<string>
     */
    private function targetUrls(Customer $customer): array
    {
        $urls = [];

        $configured = LmsHub::configuredUrl();
        if ($configured !== null) {
            $urls[TenantResolver::normalise($configured)] = $configured;
        }

        foreach ($this->legacySubscriptionUrls($customer) as $url) {
            $urls[TenantResolver::normalise($url)] = $url;
        }

        return array_values($urls);
    }

    /**
     * @return Collection<int, string>
     */
    private function legacySubscriptionUrls(Customer $customer): Collection
    {
        $typeId = (int) config('customer_sync.lms_subscription_type_id');

        return CustomerSubscription::query()
            ->where('customer_id', $customer->id)
            ->where('subscription_type_id', $typeId)
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->pluck('url')
            ->map(fn (string $url): string => rtrim($url, '/'))
            ->values();
    }

    private function client(Customer $customer): PendingRequest
    {
        $token = config('services.lms.sync_token');

        if (! is_string($token) || $token === '') {
            $token = (string) $customer->token;
        }

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('customer_sync.timeout', 30))
            ->connectTimeout(10);
    }
}
