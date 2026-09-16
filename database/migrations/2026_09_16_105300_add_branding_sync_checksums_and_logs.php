<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->string('logo_1_checksum', 128)->nullable()->after('logo_1_updated_at');
            $table->string('logo_2_checksum', 128)->nullable()->after('logo_2_updated_at');
            $table->string('logo_3_checksum', 128)->nullable()->after('logo_3_updated_at');
        });

        Schema::create('branding_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_subscription_id');
            $table->string('slot');
            $table->string('direction');
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->json('sync_data')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->foreign('customer_subscription_id')
                ->references('id')
                ->on('customer_subscriptions')
                ->onDelete('cascade');
            $table->index(['customer_subscription_id', 'slot']);
            $table->index(['status', 'synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_sync_logs');

        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['logo_1_checksum', 'logo_2_checksum', 'logo_3_checksum']);
        });
    }
};
