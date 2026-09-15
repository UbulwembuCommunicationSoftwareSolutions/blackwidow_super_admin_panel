<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One customer user per email per customer, enforced by the database.
 *
 * Without this, the tenant sync could match the wrong row and silently merge or
 * duplicate accounts. The constraint deliberately spans soft-deleted rows too:
 * the sync resurrects a tombstoned user rather than inserting a second one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->purgeTombstonedDuplicates();
        $this->guardAgainstLiveDuplicates();

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

    /**
     * Soft-deleted rows that collide with another row for the same customer are
     * superseded by definition, so they can go.
     */
    private function purgeTombstonedDuplicates(): void
    {
        foreach ($this->duplicateGroups() as $group) {
            $rows = DB::table('customer_users')
                ->where('customer_id', $group->customer_id)
                ->where('email_address', $group->email_address)
                ->orderByRaw('deleted_at is null desc')
                ->orderByDesc('updated_at')
                ->get();

            $keep = $rows->first();

            $discardable = $rows->skip(1)->filter(fn ($row) => $row->deleted_at !== null)->pluck('id');

            if ($keep !== null && $discardable->isNotEmpty()) {
                DB::table('customer_users')->whereIn('id', $discardable)->delete();
            }
        }
    }

    private function guardAgainstLiveDuplicates(): void
    {
        $remaining = $this->duplicateGroups();

        if ($remaining->isEmpty()) {
            return;
        }

        $detail = $remaining
            ->map(fn ($group) => "customer {$group->customer_id}: {$group->email_address} ({$group->total} rows)")
            ->implode('; ');

        throw new RuntimeException(
            'Cannot enforce one customer user per email: live duplicates exist and need a human decision on '
            .'which row survives. Run "php artisan app:merge-duplicate-customer-users" to review the proposed '
            .'survivors, then again with --apply, then re-run the migration. '.$detail
        );
    }

    /**
     * @return Collection<int, object>
     */
    private function duplicateGroups(): Collection
    {
        return DB::table('customer_users')
            ->select('customer_id', 'email_address', DB::raw('count(*) as total'))
            ->groupBy('customer_id', 'email_address')
            ->havingRaw('count(*) > 1')
            ->get();
    }
};
