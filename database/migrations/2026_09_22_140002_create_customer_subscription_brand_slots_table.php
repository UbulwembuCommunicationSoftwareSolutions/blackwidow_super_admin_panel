<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_subscription_brand_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_subscription_id');
            $table->string('slot');
            $table->unsignedBigInteger('customer_branding_media_id')->nullable();
            $table->boolean('is_override')->default(true);
            $table->boolean('cleared')->default(false);
            $table->timestamps();

            $table->foreign('customer_subscription_id', 'cust_sub_brand_slots_sub_fk')
                ->references('id')
                ->on('customer_subscriptions')
                ->cascadeOnDelete();
            $table->foreign('customer_branding_media_id', 'cust_sub_brand_slots_media_fk')
                ->references('id')
                ->on('customer_branding_media')
                ->nullOnDelete();

            $table->unique(['customer_subscription_id', 'slot'], 'cust_sub_brand_slots_sub_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscription_brand_slots');
    }
};
