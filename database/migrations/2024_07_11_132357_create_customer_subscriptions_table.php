<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->foreignId('subscription_type_id');
            $table->string('logo_1');
            $table->string('logo_2');
            $table->string('logo_3');
            $table->string('logo_4');
            $table->string('logo_5');
            $table->foreignId('customer_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscriptions');
    }
};
