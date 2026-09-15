<?php

use App\Services\UserSync\DuplicateCustomerUserMerger;
use App\Support\UserSync\DuplicateEmailGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One customer user per email per customer, enforced by the database.
 *
 * Without this, the tenant sync could match the wrong row and silently merge or
 * duplicate accounts. The constraint deliberately spans soft-deleted rows too:
 * the sync resurrects a tombstoned user rather than inserting a second one.
 *
 * Existing duplicates are collapsed by DuplicateCustomerUserMerger, which keeps
 * whichever row the tenant apps are most likely already pointing at and gives it
 * every access flag and profile field the other rows had. The one case it will
 * not decide is a group where two rows are already linked to different tenant
 * users: that is two people sharing an email, and picking a survivor there is a
 * business decision, so the migration stops and names the rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        $merger = app(DuplicateCustomerUserMerger::class);

        $this->mergeResolvableDuplicates($merger);
        $this->guardAgainstUndecidableDuplicates($merger);

        Schema::table('customer_users', function (Blueprint $table) {
            $table->unique(['customer_id', 'email_address'], 'customer_users_customer_id_email_address_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            $table->dropUnique('customer_users_customer_id_email_address_unique');
        });
    }

    private function mergeResolvableDuplicates(DuplicateCustomerUserMerger $merger): void
    {
        $merged = $this->resolvable($merger);

        foreach ($merged as $group) {
            Log::warning('Collapsing duplicate customer users to enforce one user per email', [
                'customer_id' => $group->customerId,
                'email_address' => $group->email,
                'kept' => $group->keep->id,
                'removed' => $group->discard->pluck('id')->all(),
                'inherited' => array_keys($group->updates),
            ]);

            $merger->merge($group);
        }

        if ($merged->isNotEmpty()) {
            echo sprintf(
                '%s  Collapsed %d duplicate email group(s), removed %d customer user row(s). See the log for details.%s',
                PHP_EOL,
                $merged->count(),
                $merged->sum(fn (DuplicateEmailGroup $group) => $group->discard->count()),
                PHP_EOL,
            );
        }
    }

    private function guardAgainstUndecidableDuplicates(DuplicateCustomerUserMerger $merger): void
    {
        $remaining = $merger->plan();

        if ($remaining->isEmpty()) {
            return;
        }

        $detail = $remaining
            ->map(fn (DuplicateEmailGroup $group) => sprintf(
                'customer %d: %s (ids %s)',
                $group->customerId,
                $group->email,
                $group->rows()->pluck('id')->implode(', '),
            ))
            ->implode('; ');

        throw new RuntimeException(
            'Cannot enforce one customer user per email: these groups have more than one row linked to a tenant '
            .'user, so which row survives is a human decision. Pick one per group with '
            .'"php artisan app:merge-duplicate-customer-users --keep=<id> --apply", then re-run the migration. '
            .$detail
        );
    }

    /**
     * @return Collection<int, DuplicateEmailGroup>
     */
    private function resolvable(DuplicateCustomerUserMerger $merger): Collection
    {
        return $merger->plan()
            ->reject(fn (DuplicateEmailGroup $group) => $group->hasCompetingTenantLinks())
            ->values();
    }
};
