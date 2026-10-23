<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            // When the stored env rows were last known to match the site's real .env (a successful push, or a pull).
            // An env row edited after this has not reached the site yet, so a pull must not overwrite it.
            $table->timestamp('env_synced_at')->nullable();
        });

        // Rows are treated as in sync with their site from the moment this ships.
        DB::table('customer_subscriptions')
            ->whereNotNull('forge_site_id')
            ->where('forge_site_id', '<>', '')
            ->update(['env_synced_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropColumn('env_synced_at');
        });
    }
};
