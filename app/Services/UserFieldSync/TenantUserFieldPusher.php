<?php

namespace App\Services\UserFieldSync;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\CustomerUserField;
use App\Models\UserFieldSyncLog;
use App\Support\CustomerSync\LmsHub;
use App\Support\UserFieldSync\UserFieldSyncPayload;
use App\Support\UserFieldSync\UserFieldValueSyncPayload;
use App\Support\UserSync\TenantResolver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TenantUserFieldPusher
{
    public function pushDefinition(CustomerUserField $field, string $operation = 'upsert'): void
    {
        if (! config('user_field_sync.enabled', true)) {
            return;
        }

        $field->loadMissing('customer');
        $customer = $field->customer;
        if ($customer === null) {
            return;
        }

        $payload = UserFieldSyncPayload::fromModel($field);
        $path = match ($operation) {
            'archive' => '/admin-api/v1/sync/user-fields/archive',
            'restore' => '/admin-api/v1/sync/user-fields/restore',
            default => '/admin-api/v1/sync/user-fields',
        };

        foreach ($this->tenantSubscriptions($customer) as $target) {
            $this->postToTenant(
                $target,
                $path,
                [
                    'app_url' => $target->url,
                    'origin' => 'super_admin',
                    'user_field' => $payload->toArray(),
                ],
                'definition',
                $field->name,
                $customer->id,
            );
        }

        if (config('user_field_sync.lms_hub_enabled', true)) {
            foreach ($this->lmsTargets($customer) as $target) {
                $this->postToLms(
                    $target['subscription'],
                    $target['url'],
                    $path,
                    [
                        'app_url' => $target['url'],
                        'origin' => 'super_admin',
                        'user_field' => $payload->toLmsArray($customer->id),
                    ],
                    'definition',
                    $field->name,
                    $customer->id,
                );
            }
        }
    }

    public function pushValuesForUser(CustomerUser $user): void
    {
        if (! config('user_field_sync.enabled', true)) {
            return;
        }

        $user->loadMissing('customer', 'fieldValues.field');
        $customer = $user->customer;
        if ($customer === null) {
            return;
        }

        $payload = UserFieldValueSyncPayload::fromUser($user, $user->fieldValues);
        if ($payload->values === []) {
            return;
        }

        $path = '/admin-api/v1/sync/user-field-values';

        foreach ($this->tenantSubscriptions($customer) as $target) {
            $this->postToTenant(
                $target,
                $path,
                [
                    'app_url' => $target->url,
                    'origin' => 'super_admin',
                    'user_field_values' => $payload->toArray(),
                ],
                'values',
                (string) $user->id,
                $customer->id,
            );
        }

        if (config('user_field_sync.lms_hub_enabled', true)) {
            foreach ($this->lmsTargets($customer) as $target) {
                $this->postToLms(
                    $target['subscription'],
                    $target['url'],
                    $path,
                    [
                        'app_url' => $target['url'],
                        'origin' => 'super_admin',
                        'user_field_values' => $payload->toLmsArray($customer->id),
                    ],
                    'values',
                    (string) $user->id,
                    $customer->id,
                );
            }
        }
    }

    /**
     * @return Collection<int, CustomerSubscription>
     */
    private function tenantSubscriptions(Customer $customer): Collection
    {
        $typeIds = array_map('intval', (array) config('user_field_sync.tenant_subscription_types', [1, 2]));

        return CustomerSubscription::query()
            ->where('customer_id', $customer->id)
            ->whereIn('subscription_type_id', $typeIds)
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->get()
            ->filter(fn (CustomerSubscription $s): bool => filled($customer->token))
            ->values();
    }

    /**
     * @return Collection<int, CustomerSubscription>
     */
    private function lmsSubscriptions(Customer $customer): Collection
    {
        $typeId = (int) config('user_field_sync.lms_subscription_type_id');

        return CustomerSubscription::query()
            ->where('customer_id', $customer->id)
            ->where('subscription_type_id', $typeId)
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->get()
            ->values();
    }

    /**
     * @return list<array{url: string, subscription: ?CustomerSubscription}>
     */
    private function lmsTargets(Customer $customer): array
    {
        $subscriptions = $this->lmsSubscriptions($customer);
        $targets = [];

        $configured = LmsHub::configuredUrl();
        if ($configured !== null) {
            $targets[TenantResolver::normalise($configured)] = [
                'url' => $configured,
                'subscription' => $subscriptions->first(
                    fn (CustomerSubscription $row): bool => TenantResolver::normalise($row->url) === TenantResolver::normalise($configured)
                ),
            ];
        }

        foreach ($subscriptions as $subscription) {
            $url = rtrim((string) $subscription->url, '/');
            $key = TenantResolver::normalise($url);
            if (! isset($targets[$key])) {
                $targets[$key] = [
                    'url' => $url,
                    'subscription' => $subscription,
                ];
            }
        }

        return array_values($targets);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postToTenant(
        CustomerSubscription $target,
        string $path,
        array $body,
        string $entityType,
        string $entityKey,
        int $customerId,
    ): void {
        $url = rtrim((string) $target->url, '/').$path;

        try {
            $response = $this->tenantClient($target)->post($url, $body);
        } catch (\Throwable $e) {
            $this->log($target, $customerId, $entityType, $entityKey, 'failed', $e->getMessage(), [
                'url' => $url,
                'request' => $body,
            ]);
            throw $e;
        }

        if (! $response->successful()) {
            $this->log($target, $customerId, $entityType, $entityKey, 'failed', 'HTTP '.$response->status(), [
                'url' => $url,
                'request' => $body,
                'response_body' => $response->body(),
            ]);

            throw new \RuntimeException('Tenant user-field sync failed with HTTP '.$response->status().' for '.$url);
        }

        $this->log($target, $customerId, $entityType, $entityKey, 'success', null, [
            'url' => $url,
            'outcome' => $response->json('outcome'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postToLms(
        ?CustomerSubscription $lmsSubscription,
        string $hubUrl,
        string $path,
        array $body,
        string $entityType,
        string $entityKey,
        int $customerId,
    ): void {
        $url = rtrim($hubUrl, '/').$path;

        try {
            $response = $this->lmsClient()->post($url, $body);
        } catch (\Throwable $e) {
            $this->log($lmsSubscription, $customerId, $entityType, $entityKey, 'failed', $e->getMessage(), [
                'url' => $url,
                'request' => $body,
            ]);
            throw $e;
        }

        if (! $response->successful()) {
            $this->log($lmsSubscription, $customerId, $entityType, $entityKey, 'failed', 'HTTP '.$response->status(), [
                'url' => $url,
                'request' => $body,
                'response_body' => $response->body(),
            ]);

            throw new \RuntimeException('LMS user-field sync failed with HTTP '.$response->status().' for '.$url);
        }

        $this->log($lmsSubscription, $customerId, $entityType, $entityKey, 'success', null, [
            'url' => $url,
            'outcome' => $response->json('outcome'),
        ]);
    }

    private function tenantClient(CustomerSubscription $subscription): PendingRequest
    {
        $subscription->loadMissing('customer');

        return Http::withToken((string) $subscription->customer->token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('user_field_sync.timeout', 30))
            ->connectTimeout(10);
    }

    private function lmsClient(): PendingRequest
    {
        $token = config('services.lms.sync_token');
        if (! is_string($token) || $token === '') {
            Log::warning('LMS sync token is not configured; skipping LMS user-field push');
            throw new \RuntimeException('LMS sync token is not configured (services.lms.sync_token).');
        }

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('user_field_sync.timeout', 30))
            ->connectTimeout(10);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function log(
        ?CustomerSubscription $subscription,
        int $customerId,
        string $entityType,
        string $entityKey,
        string $status,
        ?string $error = null,
        ?array $data = null,
    ): void {
        UserFieldSyncLog::create([
            'customer_subscription_id' => $subscription?->id,
            'customer_id' => $customerId,
            'entity_type' => $entityType,
            'entity_key' => $entityKey,
            'direction' => 'outbound',
            'status' => $status,
            'error_message' => $error,
            'sync_data' => $data,
            'synced_at' => now(),
        ]);
    }
}
