<?php

namespace App\Services\UserSync;

use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\ProductPermission;
use App\Support\UserSync\SyncOutcome;
use App\Support\UserSync\UserSyncPayload;
use Illuminate\Support\Facades\Log;

/**
 * Applies user changes that a tenant app pushed to us.
 *
 * Every inbound write goes through here so the identity resolution, conflict
 * rule and echo suppression are defined exactly once, whether the request came
 * from the canonical /api/v1/sync/users endpoints or a legacy alias.
 */
class CustomerUserSyncService
{
    /**
     * Resolve, then create or update, the customer user described by $payload.
     *
     * @param  string|null  $password  Cleartext, never a hash. Supplying one always
     *                                 sets the password; routine profile syncs omit
     *                                 it so they can never clobber credentials.
     * @return array{outcome: SyncOutcome, user: CustomerUser}
     */
    public function upsertFromTenant(
        CustomerSubscription $subscription,
        UserSyncPayload $payload,
        ?string $password = null,
    ): array {
        $user = $this->locate($subscription, $payload);

        if (! $user) {
            return [
                'outcome' => SyncOutcome::Created,
                'user' => $this->create($subscription, $payload, $password),
            ];
        }

        // Establish the return link even when the profile itself is stale, so a
        // tenant that has never told us its local id stops being anonymous.
        $this->adoptTenantId($user, $payload);

        if ($this->incomingIsStale($user, $payload)) {
            Log::info('Inbound user sync ignored: our copy is newer', [
                'customer_user_id' => $user->id,
                'ours' => $user->updated_at?->toIso8601String(),
                'theirs' => $payload->updatedAt?->toIso8601String(),
            ]);

            return ['outcome' => SyncOutcome::Stale, 'user' => $user->fresh()];
        }

        return [
            'outcome' => SyncOutcome::Updated,
            'user' => $this->update($user, $payload, $password, $subscription),
        ];
    }

    public function archiveFromTenant(CustomerSubscription $subscription, UserSyncPayload $payload): ?CustomerUser
    {
        $user = $this->locate($subscription, $payload);

        if (! $user) {
            return null;
        }

        $this->adoptTenantId($user, $payload);

        $user->skip_sync = true;
        $user->scheduleDelete();

        return $user->fresh();
    }

    public function restoreFromTenant(CustomerSubscription $subscription, UserSyncPayload $payload): ?CustomerUser
    {
        $user = $this->locate($subscription, $payload);

        if (! $user) {
            return null;
        }

        $this->adoptTenantId($user, $payload);

        $user->skip_sync = true;
        $user->clearDeleteSchedule();

        return $user->fresh();
    }

    /**
     * @param  string  $password  Cleartext; the model hashes it on assignment.
     */
    public function setPasswordFromTenant(
        CustomerSubscription $subscription,
        UserSyncPayload $payload,
        string $password,
    ): ?CustomerUser {
        $user = $this->locate($subscription, $payload);

        if (! $user) {
            return null;
        }

        $this->adoptTenantId($user, $payload);

        $user->skip_sync = true;
        $user->password = $password;
        $user->save();

        return $user->fresh();
    }

    /**
     * Find the customer user this payload refers to, scoped to the tenant's customer.
     *
     * Ids are tried before email: email is only an adoption path for users that
     * predate the two-way link, and matching on it first is how records get
     * merged into the wrong account.
     */
    public function locate(CustomerSubscription $subscription, UserSyncPayload $payload): ?CustomerUser
    {
        $scoped = fn () => CustomerUser::withTrashed()->where('customer_id', $subscription->customer_id);

        if ($payload->superAdminUserId !== null) {
            $user = $scoped()->whereKey($payload->superAdminUserId)->first();

            if ($user) {
                return $user;
            }
        }

        if ($payload->cmsUserId !== null) {
            $user = $scoped()->where('cms_user_id', $payload->cmsUserId)->first();

            if ($user) {
                return $user;
            }
        }

        if ($payload->email === '') {
            return null;
        }

        return $scoped()->where('email_address', $payload->email)->first();
    }

    /**
     * Our record wins when it was modified more recently than the tenant's.
     * A payload with no timestamp is treated as authoritative, since the caller
     * is asserting a change rather than replaying one.
     */
    private function incomingIsStale(CustomerUser $user, UserSyncPayload $payload): bool
    {
        if ($payload->updatedAt === null || $user->updated_at === null) {
            return false;
        }

        return $user->updated_at->greaterThan($payload->updatedAt);
    }

    private function create(
        CustomerSubscription $subscription,
        UserSyncPayload $payload,
        ?string $password,
    ): CustomerUser {
        $attributes = array_merge($payload->toCustomerUserAttributes(), [
            'customer_id' => $subscription->customer_id,
            'cms_user_id' => $payload->cmsUserId,
            'password' => $password ?? str()->password(32),
            'skip_sync' => true,
        ]);

        $user = CustomerUser::create($attributes);

        $this->grantSubscriptionAccess($user, $subscription);
        $this->syncSuperAdminPanelAccess($user, $subscription, $payload);

        Log::info('Customer user created from tenant sync', [
            'customer_user_id' => $user->id,
            'cms_user_id' => $user->cms_user_id,
            'customer_id' => $user->customer_id,
        ]);

        return $user->fresh();
    }

    private function update(
        CustomerUser $user,
        UserSyncPayload $payload,
        ?string $password,
        CustomerSubscription $subscription,
    ): CustomerUser {
        if ($user->trashed()) {
            $user->restore();
        }

        $user->skip_sync = true;
        $user->fill($payload->toCustomerUserAttributes());
        $user->delete_scheduled = null;

        if ($password !== null) {
            $user->password = $password;
        }

        $user->save();
        $this->syncSuperAdminPanelAccess($user, $subscription, $payload);

        Log::info('Customer user updated from tenant sync', [
            'customer_user_id' => $user->id,
            'cms_user_id' => $user->cms_user_id,
        ]);

        return $user->fresh();
    }

    private function syncSuperAdminPanelAccess(
        CustomerUser $user,
        CustomerSubscription $subscription,
        UserSyncPayload $payload,
    ): void {
        if ($payload->superAdminPanelAccess === null) {
            return;
        }

        $product = match ((int) $subscription->subscription_type_id) {
            1 => 'console',
            2 => 'firearm',
            default => null,
        };

        if ($product === null) {
            return;
        }

        $permission = ProductPermission::query()
            ->where('product', $product)
            ->where('name', 'access super admin')
            ->first();

        if ($permission === null) {
            return;
        }

        if ($payload->superAdminPanelAccess) {
            $user->productPermissions()->syncWithoutDetaching([$permission->id]);
        } else {
            $user->productPermissions()->detach($permission->id);
        }
    }

    /**
     * Record the tenant's local user id the first time we learn it.
     */
    private function adoptTenantId(CustomerUser $user, UserSyncPayload $payload): void
    {
        if ($payload->cmsUserId === null || $user->cms_user_id === $payload->cmsUserId) {
            return;
        }

        $user->cms_user_id = $payload->cmsUserId;
        $user->saveQuietly();
    }

    /**
     * A user created from a tenant must at minimum be able to use that tenant.
     */
    private function grantSubscriptionAccess(CustomerUser $user, CustomerSubscription $subscription): void
    {
        $flag = UserSyncPayload::flagForSubscriptionType((int) $subscription->subscription_type_id);

        if ($flag === null || $user->{$flag}) {
            return;
        }

        $user->skip_sync = true;
        $user->{$flag} = true;
        $user->save();
    }
}
