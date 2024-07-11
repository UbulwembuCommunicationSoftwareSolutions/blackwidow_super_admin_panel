<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('app_url');
            $table->foreignId('customer_id');
            $table->string('console_login_logo');
            $table->string('console_menu_logo');
            $table->string('console_background_logo');
            $table->string('app_install_logo');
            $table->string('app_background_logo');
            $table->foreignId('subscription_type_id');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscriptions');
    }
};
