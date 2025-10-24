<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            $table->string('super_admin_user_id')->nullable()->unique()->after('id');
            $table->timestamp('last_synced_at')->nullable()->after('super_admin_user_id');
            $table->string('sync_hash')->nullable()->after('last_synced_at');
            $table->boolean('skip_sync')->default(false)->after('sync_hash');
            
            $table->index('super_admin_user_id');
            $table->index('last_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            $table->dropIndex(['super_admin_user_id']);
            $table->dropIndex(['last_synced_at']);
            $table->dropColumn(['super_admin_user_id', 'last_synced_at', 'sync_hash', 'skip_sync']);
        });
    }
};
