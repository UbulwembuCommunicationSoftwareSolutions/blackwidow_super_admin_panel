<?php

namespace App\Console\Commands\OneTimeFixes;

use App\Models\CustomerUser;
use App\Services\UserSync\DuplicateCustomerUserMerger;
use App\Support\UserSync\DuplicateEmailGroup;
use App\Support\UserSync\UserSyncPayload;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Clears the way for the one-user-per-email-per-customer unique index.
 *
 * Reports by default; only writes with --apply.
 */
class MergeDuplicateCustomerUsers extends Command
{
    protected $signature = 'app:merge-duplicate-customer-users
        {--customer= : Only look at one customer id}
        {--keep= : Force this customer_users.id to be the survivor of its group}
        {--apply : Perform the merge instead of only reporting it}';

    protected $description = 'Collapse customer users that share an email inside the same customer down to one row';

    public function handle(DuplicateCustomerUserMerger $merger): int
    {
        $groups = $this->resolveGroups($merger);

        if ($groups === null) {
            return self::FAILURE;
        }

        if ($groups->isEmpty()) {
            $this->components->info('No customer users share an email inside the same customer.');

            return self::SUCCESS;
        }

        $mergeable = $groups->filter(fn (DuplicateEmailGroup $group) => $this->isMergeable($group));

        foreach ($groups as $group) {
            $this->reportGroup($group, $this->isMergeable($group));
        }

        if (! $this->option('apply')) {
            $this->components->warn('Nothing was written. Re-run with --apply once the survivors above look right.');

            return self::SUCCESS;
        }

        foreach ($mergeable as $group) {
            $merger->merge($group);
        }

        $this->components->info(sprintf(
            '%d duplicate email group(s) merged, %d row(s) removed.',
            $mergeable->count(),
            $mergeable->sum(fn (DuplicateEmailGroup $group) => $group->discard->count()),
        ));

        $skipped = $groups->count() - $mergeable->count();

        if ($skipped > 0) {
            $this->components->warn("{$skipped} group(s) left alone: pick the survivor with --keep=<id>.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, DuplicateEmailGroup>|null Null when the options do not describe a real group.
     */
    private function resolveGroups(DuplicateCustomerUserMerger $merger): ?Collection
    {
        $keepId = $this->option('keep');

        if ($keepId === null) {
            $customerId = $this->option('customer');

            return $merger->plan($customerId === null ? null : (int) $customerId);
        }

        if (! CustomerUser::withTrashed()->whereKey($keepId)->exists()) {
            $this->components->error("No customer user with id {$keepId}.");

            return null;
        }

        $group = $merger->planKeeping((int) $keepId);

        if (! $group) {
            $this->components->error("Customer user {$keepId} does not share its email with another row.");

            return null;
        }

        return collect([$group]);
    }

    /**
     * A group whose survivor the ranking cannot pick on its own waits for a
     * --keep, so an ambiguous merge is never performed behind someone's back.
     */
    private function isMergeable(DuplicateEmailGroup $group): bool
    {
        if (! $group->hasCompetingTenantLinks()) {
            return true;
        }

        return $this->option('keep') !== null && $group->keep->id === (int) $this->option('keep');
    }

    private function reportGroup(DuplicateEmailGroup $group, bool $mergeable): void
    {
        $this->newLine();
        $this->line(sprintf(
            '<comment>customer %d</comment> %s <fg=gray>(%d rows)</>',
            $group->customerId,
            $group->email,
            $group->rows()->count(),
        ));

        $this->table(
            ['', 'id', 'cms_user_id', 'name', 'access', 'last synced', 'updated', 'deleted'],
            $group->rows()->map(fn (CustomerUser $user) => [
                $user->is($group->keep) ? '<info>keep</info>' : 'remove',
                $user->id,
                $user->cms_user_id ?? '—',
                trim("{$user->first_name} {$user->last_name}") ?: '—',
                $this->accessSummary($user),
                $user->last_synced_at?->toDateTimeString() ?? '—',
                $user->updated_at?->toDateTimeString() ?? '—',
                $user->deleted_at?->toDateTimeString() ?? '—',
            ])->all(),
        );

        if ($group->updates !== []) {
            $this->line('  survivor inherits: '.collect($group->updates)
                ->map(fn ($value, $key) => $key.'='.($value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value))
                ->implode(', '));
        }

        if (! $mergeable) {
            $this->components->warn(
                'More than one of these rows is linked to a tenant user, so the survivor is a human decision. '
                .'Re-run with --keep=<id> --apply for this group once you know which link is the real one.'
            );
        }
    }

    private function accessSummary(CustomerUser $user): string
    {
        $granted = collect(array_keys(UserSyncPayload::ACCESS_FLAGS))
            ->filter(fn (string $flag) => (bool) $user->{$flag})
            ->map(fn (string $flag) => str($flag)->beforeLast('_access')->toString())
            ->when($user->is_system_admin, fn (Collection $flags) => $flags->prepend('system_admin'));

        return $granted->implode(', ') ?: '—';
    }
}
