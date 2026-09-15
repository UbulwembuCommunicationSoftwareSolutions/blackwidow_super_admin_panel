<?php

namespace App\Services\UserSync;

use App\Models\CustomerUser;
use App\Models\UserSyncLog;
use App\Support\UserSync\DuplicateEmailGroup;
use App\Support\UserSync\UserSyncPayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Collapses the rows that share an email inside one customer down to one row, so
 * that `customer_id, email_address` can carry a unique index.
 *
 * The survivor is whichever row the tenant apps are most likely already pointing
 * at, because a tenant stores this panel's id: a live row before a tombstoned
 * one, a row linked to a tenant user before an unlinked one, then the most
 * recently synced, the most recently touched, and finally the oldest id. The
 * survivor inherits every access flag and every profile field the discarded rows
 * had, so a merge can only ever widen access, never quietly revoke it.
 */
class DuplicateCustomerUserMerger
{
    /**
     * Every duplicated email, with its proposed merge.
     *
     * @return Collection<int, DuplicateEmailGroup>
     */
    public function plan(?int $customerId = null): Collection
    {
        return $this->duplicatedEmails($customerId)
            ->map(fn (object $row) => $this->planFor((int) $row->customer_id, (string) $row->email_address))
            ->filter()
            ->values();
    }

    /**
     * The merge for the group containing $customerUserId, with that row forced
     * to survive. This is the escape hatch for a group the ranking cannot call.
     */
    public function planKeeping(int $customerUserId): ?DuplicateEmailGroup
    {
        $keeper = CustomerUser::withTrashed()->find($customerUserId);

        if (! $keeper) {
            return null;
        }

        return $this->planFor((int) $keeper->customer_id, (string) $keeper->email_address, $customerUserId);
    }

    public function planFor(int $customerId, string $email, ?int $keepId = null): ?DuplicateEmailGroup
    {
        $rows = CustomerUser::withTrashed()
            ->where('customer_id', $customerId)
            ->where('email_address', $email)
            ->get()
            ->sort(fn (CustomerUser $a, CustomerUser $b) => $this->rank($a, $keepId) <=> $this->rank($b, $keepId))
            ->values();

        if ($rows->count() < 2) {
            return null;
        }

        $keep = $rows->first();
        $discard = $rows->skip(1)->values();

        return new DuplicateEmailGroup(
            customerId: $customerId,
            email: $email,
            keep: $keep,
            discard: $discard,
            updates: $this->mergedAttributes($keep, $discard),
        );
    }

    /**
     * Fold the discarded rows into the survivor and remove them for good.
     *
     * Soft deletion is not enough: the unique index deliberately spans
     * tombstoned rows so the sync resurrects them instead of inserting again.
     */
    public function merge(DuplicateEmailGroup $group): void
    {
        DB::transaction(function () use ($group) {
            // Model events here would push an archive of every discarded row to
            // the tenant apps, which is exactly the account we are preserving.
            CustomerUser::withoutEvents(function () use ($group) {
                $keep = $group->keep;

                // Removal comes first: an inherited cms_user_id is still held by
                // the row it came from, and that column is unique per customer.
                foreach ($group->discard as $user) {
                    UserSyncLog::query()
                        ->where('customer_user_id', $user->id)
                        ->update(['customer_user_id' => $keep->id]);

                    $user->tokens()->delete();
                    $user->forceDelete();
                }

                if ($group->updates !== []) {
                    $keep->forceFill($group->updates);
                    // Conflict resolution compares our updated_at against the
                    // tenant's, so housekeeping must not make our copy look new.
                    $keep->timestamps = false;
                    $keep->save();
                }
            });
        });
    }

    /**
     * @return Collection<int, object{customer_id: int, email_address: string}>
     */
    private function duplicatedEmails(?int $customerId = null): Collection
    {
        return CustomerUser::withTrashed()
            ->select('customer_id', 'email_address')
            ->when($customerId !== null, fn ($query) => $query->where('customer_id', $customerId))
            ->groupBy('customer_id', 'email_address')
            ->havingRaw('count(*) > 1')
            ->orderBy('customer_id')
            ->orderBy('email_address')
            ->get()
            ->map(fn (CustomerUser $row) => (object) [
                'customer_id' => (int) $row->customer_id,
                'email_address' => (string) $row->email_address,
            ]);
    }

    /**
     * Sort key for survivor preference: lower sorts first.
     *
     * @return array<int, int>
     */
    private function rank(CustomerUser $user, ?int $keepId = null): array
    {
        return [
            $user->id === $keepId ? 0 : 1,
            $user->trashed() ? 1 : 0,
            $user->cms_user_id === null ? 1 : 0,
            -($user->last_synced_at?->getTimestamp() ?? 0),
            -($user->updated_at?->getTimestamp() ?? 0),
            $user->id,
        ];
    }

    /**
     * @param  Collection<int, CustomerUser>  $discard
     * @return array<string, mixed>
     */
    private function mergedAttributes(CustomerUser $keep, Collection $discard): array
    {
        $updates = [];

        $flags = [...array_keys(UserSyncPayload::ACCESS_FLAGS), 'is_system_admin'];

        foreach ($flags as $flag) {
            if (! $keep->{$flag} && $discard->contains(fn (CustomerUser $user) => (bool) $user->{$flag})) {
                $updates[$flag] = true;
            }
        }

        foreach (['cms_user_id', 'first_name', 'last_name', 'cellphone'] as $attribute) {
            if (filled($keep->{$attribute})) {
                continue;
            }

            $inherited = $discard->first(fn (CustomerUser $user) => filled($user->{$attribute}));

            if ($inherited) {
                $updates[$attribute] = $inherited->{$attribute};
            }
        }

        $earliestCreatedAt = $discard->pluck('created_at')->filter()->sort()->first();

        if ($earliestCreatedAt && $keep->created_at?->greaterThan($earliestCreatedAt)) {
            $updates['created_at'] = $earliestCreatedAt;
        }

        return $updates;
    }
}
