<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarded so it can complete over a partial run: MySQL DDL is not transactional,
     * and an earlier version failed after adding the columns and creating the table.
     * Constraint names are explicit because MySQL caps identifiers at 64 characters.
     */
    public function up(): void
    {
        Schema::table('product_permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('product_permissions', 'guard_name')) {
                $table->string('guard_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('product_permissions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sub_group_name');
            }
            if (! Schema::hasColumn('product_permissions', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('is_active');
            }
        });

        Schema::dropIfExists('customer_user_subscription_permissions');

        Schema::create('customer_user_subscription_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_user_id');
            $table->unsignedBigInteger('customer_subscription_id');
            $table->unsignedBigInteger('product_permission_id');
            $table->timestamps();

            $table->foreign('customer_user_id', 'cusp_customer_user_foreign')
                ->references('id')->on('customer_users')->cascadeOnDelete();
            $table->foreign('customer_subscription_id', 'cusp_customer_subscription_foreign')
                ->references('id')->on('customer_subscriptions')->cascadeOnDelete();
            $table->foreign('product_permission_id', 'cusp_product_permission_foreign')
                ->references('id')->on('product_permissions')->cascadeOnDelete();

            $table->unique(
                ['customer_user_id', 'customer_subscription_id', 'product_permission_id'],
                'customer_user_subscription_permission_unique'
            );
            $table->index(['customer_subscription_id', 'customer_user_id'], 'cusp_subscription_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_user_subscription_permissions');

        Schema::table('product_permissions', function (Blueprint $table) {
            $table->dropColumn(['guard_name', 'is_active', 'last_seen_at']);
        });
    }
};
