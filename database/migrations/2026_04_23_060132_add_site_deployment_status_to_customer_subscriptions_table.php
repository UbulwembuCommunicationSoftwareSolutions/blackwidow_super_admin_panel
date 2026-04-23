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
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->timestamp('site_deployment_queue_started_at')->nullable();
            $table->text('last_deployment_error')->nullable();
            $table->timestamp('last_deployment_error_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'site_deployment_queue_started_at',
                'last_deployment_error',
                'last_deployment_error_at',
            ]);
        });
    }
};
