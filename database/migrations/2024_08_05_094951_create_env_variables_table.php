<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('env_variables', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('value');
            $table->foreignId('customer_subscription_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('env_variables');
    }
};
