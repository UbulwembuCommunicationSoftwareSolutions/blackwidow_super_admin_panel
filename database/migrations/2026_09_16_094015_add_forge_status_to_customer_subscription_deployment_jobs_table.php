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
        Schema::table('customer_subscription_deployment_jobs', function (Blueprint $table) {
            $table->string('forge_status')->nullable()->after('error_message');
            $table->longText('forge_log')->nullable()->after('forge_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_subscription_deployment_jobs', function (Blueprint $table) {
            $table->dropColumn(['forge_status', 'forge_log']);
        });
    }
};
