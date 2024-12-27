<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nginx_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('server_id');
            $table->integer('template_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nginx_templates');
    }
};
