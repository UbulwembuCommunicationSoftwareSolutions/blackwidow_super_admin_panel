<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_permissions', function (Blueprint $table) {
            $table->string('guard_name')->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('sub_group_name');
            $table->timestamp('last_seen_at')->nullable()->after('is_active');
        });

        Schema::create('customer_user_subscription_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_user_id')->constrained('customer_users')->cascadeOnDelete();
            $table->foreignId('customer_subscription_id')->constrained('customer_subscriptions')->cascadeOnDelete();
            $table->foreignId('product_permission_id')->constrained('product_permissions')->cascadeOnDelete();
            $table->timestamps();

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
