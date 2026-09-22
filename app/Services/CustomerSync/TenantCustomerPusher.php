<?php

namespace App\Services\CustomerSync;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Support\CustomerSync\CustomerSyncPayload;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TenantCustomerPusher
{
    public function upsert(Customer $customer): void
    {
        if (! config('customer_sync.enabled', true)) {
            return;
        }

        $targets = $this->targetSubscriptions($customer);

        if ($targets->isEmpty()) {
            Log::info('No LMS tenants to push customer to', [
                'customer_id' => $customer->id,
            ]);

            return;
        }

        $payload = CustomerSyncPayload::fromCustomer($customer);

        foreach ($targets as $subscription) {
            $this->push($customer, $subscription, $payload);
        }
    }

    private function push(
        Customer $customer,
        CustomerSubscription $subscription,
        CustomerSyncPayload $payload,
    ): void {
        $url = rtrim((string) $subscription->url, '/').'/admin-api/v1/sync/customers';

        $body = array_merge($payload->toArray(), [
            'app_url' => $subscription->url,
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
     * @return Collection<int, CustomerSubscription>
     */
    private function targetSubscriptions(Customer $customer): Collection
    {
        $typeId = (int) config('customer_sync.lms_subscription_type_id');

        return CustomerSubscription::query()
            ->where('customer_id', $customer->id)
            ->where('subscription_type_id', $typeId)
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->get()
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
