<?php

namespace App\Services\Permissions;

use App\Models\CustomerSubscription;
use App\Models\ProductPermission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Pulls a tenant app's Spatie permission catalog over the canonical contract
 * (GET {tenant}/admin-api/v1/sync/permissions) and mirrors it into
 * product_permissions so grants can reference stable rows.
 */
class TenantPermissionCatalogService
{
    public const DEFAULT_GROUP = 'General';

    /**
     * Catalog for the subscription, fetched from the tenant unless a recent copy is cached.
     *
     * @return array{
     *     product: string,
     *     guard: string|null,
     *     source: 'tenant'|'cache'|'stored',
     *     fetched_at: string|null,
     *     error: string|null,
     *     permissions: list<array{name: string, group: string, sub_group: string}>,
     *     groups: list<array{name: string, sub_groups: list<array{name: string, permissions: list<string>}>}>
     * }
     */
    public function catalog(CustomerSubscription $subscription, bool $refresh = false): array
    {
        $cacheKey = $this->cacheKey($subscription);

        if (! $refresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return array_merge($cached, ['source' => 'cache']);
            }
        }

        try {
            $fetched = $this->refresh($subscription);
        } catch (Throwable $e) {
            Log::warning('Tenant permission catalog fetch failed, using stored catalog', [
                'customer_subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return $this->present($subscription, $this->storedPermissions($subscription), null, 'stored', null, $e->getMessage());
        }

        Cache::put($cacheKey, $fetched, (int) config('user_sync.permission_catalog_ttl', 600));

        return $fetched;
    }

    /**
     * Fetch the catalog from the tenant and upsert it into product_permissions.
     *
     * @return array<string, mixed>
     */
    public function refresh(CustomerSubscription $subscription): array
    {
        $subscription->loadMissing('customer');
        $baseUrl = $subscription->tenantBaseUrl();
        $token = $subscription->tenantToken();

        if ($baseUrl === null || $token === '') {
            throw new RuntimeException('Subscription has no reachable tenant URL or token.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) config('user_sync.timeout', 15))
            ->connectTimeout(10)
            ->get($baseUrl.'/admin-api/v1/sync/permissions');

        if (! $response->successful()) {
            throw new RuntimeException('Tenant permission catalog returned HTTP '.$response->status());
        }

        $guard = $response->json('guard');
        $permissions = $this->normalise((array) $response->json('permissions', []));

        $this->store($subscription->permissionProduct(), is_string($guard) ? $guard : null, $permissions);

        return $this->present($subscription, $permissions, is_string($guard) ? $guard : null, 'tenant', now()->toIso8601String(), null);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array{name: string, group: string, sub_group: string}>
     */
    private function normalise(array $rows): array
    {
        return collect($rows)
            ->map(function (mixed $row): ?array {
                if (is_string($row)) {
                    $row = ['name' => $row];
                }

                if (! is_array($row) || blank($row['name'] ?? null)) {
                    return null;
                }

                return [
                    'name' => (string) $row['name'],
                    'group' => filled($row['group'] ?? null) ? (string) $row['group'] : self::DEFAULT_GROUP,
                    'sub_group' => filled($row['sub_group'] ?? null) ? (string) $row['sub_group'] : self::DEFAULT_GROUP,
                ];
            })
            ->filter()
            ->unique('name')
            ->values()
            ->all();
    }

    /**
     * @param  list<array{name: string, group: string, sub_group: string}>  $permissions
     */
    private function store(string $product, ?string $guard, array $permissions): void
    {
        $now = now();
        $names = array_column($permissions, 'name');

        $rows = array_map(fn (array $permission): array => [
            'product' => $product,
            'name' => $permission['name'],
            'guard_name' => $guard,
            'group_name' => $permission['group'],
            'sub_group_name' => $permission['sub_group'],
            'is_active' => true,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $permissions);

        foreach (array_chunk($rows, 500) as $chunk) {
            ProductPermission::query()->upsert(
                $chunk,
                ['product', 'name'],
                ['guard_name', 'group_name', 'sub_group_name', 'is_active', 'last_seen_at', 'updated_at'],
            );
        }

        ProductPermission::query()
            ->forProduct($product)
            ->whereNotIn('name', $names)
            ->update(['is_active' => false]);
    }

    /**
     * @return list<array{name: string, group: string, sub_group: string}>
     */
    private function storedPermissions(CustomerSubscription $subscription): array
    {
        return $subscription->permissionCatalog()
            ->orderBy('group_name')
            ->orderBy('sub_group_name')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductPermission $permission): array => [
                'name' => $permission->name,
                'group' => $permission->group_name ?: self::DEFAULT_GROUP,
                'sub_group' => $permission->sub_group_name ?: self::DEFAULT_GROUP,
            ])
            ->all();
    }

    /**
     * @param  list<array{name: string, group: string, sub_group: string}>  $permissions
     * @return array<string, mixed>
     */
    private function present(
        CustomerSubscription $subscription,
        array $permissions,
        ?string $guard,
        string $source,
        ?string $fetchedAt,
        ?string $error,
    ): array {
        return [
            'product' => $subscription->permissionProduct(),
            'guard' => $guard,
            'source' => $source,
            'fetched_at' => $fetchedAt,
            'error' => $error,
            'permissions' => $permissions,
            'groups' => $this->group($permissions),
        ];
    }

    /**
     * @param  list<array{name: string, group: string, sub_group: string}>  $permissions
     * @return list<array{name: string, sub_groups: list<array{name: string, permissions: list<string>}>}>
     */
    public function group(array $permissions): array
    {
        return collect($permissions)
            ->groupBy('group')
            ->map(fn (Collection $inGroup, string $group): array => [
                'name' => $group,
                'sub_groups' => $inGroup
                    ->groupBy('sub_group')
                    ->map(fn (Collection $inSubGroup, string $subGroup): array => [
                        'name' => $subGroup,
                        'permissions' => $inSubGroup->pluck('name')->sort()->values()->all(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public function forget(CustomerSubscription $subscription): void
    {
        Cache::forget($this->cacheKey($subscription));
    }

    private function cacheKey(CustomerSubscription $subscription): string
    {
        return 'tenant-permission-catalog:'.$subscription->id;
    }
}
