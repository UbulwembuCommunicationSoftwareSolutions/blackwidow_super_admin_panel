<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->string('logo_1')->nullable()->change();
            $table->string('logo_2')->nullable()->change();
            $table->string('logo_3')->nullable()->change();
            $table->string('logo_4')->nullable()->change();
            $table->string('logo_5')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscriptions');
    }
};
