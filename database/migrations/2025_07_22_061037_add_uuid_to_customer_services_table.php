<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
