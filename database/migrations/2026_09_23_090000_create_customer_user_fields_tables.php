<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_user_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('label');
            $table->string('type');
            $table->string('rules')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('skip_sync')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['customer_id', 'name']);
            $table->index(['customer_id', 'active', 'sort_order']);
        });

        Schema::create('customer_user_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_user_field_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->boolean('skip_sync')->default(false);
            $table->timestamps();

            $table->unique(['customer_user_id', 'customer_user_field_id'], 'cufv_user_field_unique');
        });

        Schema::create('user_field_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_subscription_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('entity_type');
            $table->string('entity_key')->nullable();
            $table->string('direction');
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->json('sync_data')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->foreign('customer_subscription_id')
                ->references('id')
                ->on('customer_subscriptions')
                ->nullOnDelete();
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->nullOnDelete();
            $table->index(['status', 'synced_at']);
            $table->index(['entity_type', 'entity_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_field_sync_logs');
        Schema::dropIfExists('customer_user_field_values');
        Schema::dropIfExists('customer_user_fields');
    }
};
