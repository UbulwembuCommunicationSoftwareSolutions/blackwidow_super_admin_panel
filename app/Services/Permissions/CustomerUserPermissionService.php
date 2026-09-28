<?php

namespace App\Services\Permissions;

use App\Jobs\PushCustomerUserPermissionsJob;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\UserSyncLog;
use App\Support\UserSync\PushOperation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stores the granular permissions a customer user holds on one subscription and
 * hands them to the tenant app.
 */
class CustomerUserPermissionService
{
    /**
     * Replace the user's grants on the subscription with exactly $names.
     *
     * @param  list<string>  $names
     * @return list<string> The granted names, sorted.
     *
     * @throws ValidationException When a name is not in the subscription's catalog.
     */
    public function sync(CustomerUser $user, CustomerSubscription $subscription, array $names): array
    {
        $names = array_values(array_unique(array_map('strval', $names)));

        $permissions = $subscription->permissionCatalog()
            ->whereIn('name', $names)
            ->get(['id', 'name']);

        $unknown = array_values(array_diff($names, $permissions->pluck('name')->all()));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'Unknown permissions for this subscription: '.implode(', ', array_slice($unknown, 0, 10)),
            ]);
        }

        DB::transaction(function () use ($user, $subscription, $permissions): void {
            $user->subscriptionPermissions()
                ->wherePivot('customer_subscription_id', $subscription->id)
                ->detach();

            foreach (array_chunk($permissions->modelKeys(), 500) as $chunk) {
                $user->subscriptionPermissions()->attach($chunk, ['customer_subscription_id' => $subscription->id]);
            }
        });

        PushCustomerUserPermissionsJob::dispatch($user->id, $subscription->id);

        return $user->subscriptionPermissionNames($subscription);
    }

    /**
     * Granted names for every given user on the subscription, keyed by user id.
     *
     * @param  Collection<int, CustomerUser>  $users
     * @return array<int, list<string>>
     */
    public function grantsFor(CustomerSubscription $subscription, Collection $users): array
    {
        $users->load([
            'subscriptionPermissions' => fn ($query) => $query
                ->wherePivot('customer_subscription_id', $subscription->id)
                ->orderBy('product_permissions.name'),
        ]);

        $result = [];
        foreach ($users as $user) {
            $result[$user->id] = $user->subscriptionPermissions->pluck('name')->values()->all();
        }

        return $result;
    }

    /**
     * Latest outbound permission push per user for the subscription.
     *
     * @param  Collection<int, CustomerUser>  $users
     * @return array<int, array{status: string, error: string|null, synced_at: string|null}|null>
     */
    public function syncStatusFor(CustomerSubscription $subscription, Collection $users): array
    {
        $logs = UserSyncLog::query()
            ->whereIn('customer_user_id', $users->modelKeys())
            ->where('direction', 'outbound')
            ->where('sync_data->operation', PushOperation::Permissions->value)
            ->where('sync_data->customer_subscription_id', $subscription->id)
            ->orderByDesc('synced_at')
            ->orderByDesc('id')
            ->get()
            ->unique('customer_user_id')
            ->keyBy('customer_user_id');

        $result = [];
        foreach ($users as $user) {
            $log = $logs->get($user->id);
            $result[$user->id] = $log === null ? null : [
                'status' => $log->status,
                'error' => $log->error_message,
                'synced_at' => $log->synced_at?->toIso8601String(),
            ];
        }

        return $result;
    }
}
