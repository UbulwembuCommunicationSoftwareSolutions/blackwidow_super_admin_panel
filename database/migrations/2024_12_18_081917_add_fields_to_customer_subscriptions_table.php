<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dateTime('site_created_at')->nullable();
            $table->dateTime('github_sent_at')->nullable();
            $table->dateTime('env_sent_at')->nullable();
            $table->dateTime('deployment_script_sent_at')->nullable();
            $table->dateTime('ssl_deployed_at')->nullable();
            $table->dateTime('deployed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            //
        });
    }
};
