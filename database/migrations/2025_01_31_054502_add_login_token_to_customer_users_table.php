<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            $table->string('login_token')->nullable();
            $table->dateTime('login_token_expiry')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            $table->dropColumn('login_token');
            $table->dropColumn('login_token_expiry');
        });
    }
};
