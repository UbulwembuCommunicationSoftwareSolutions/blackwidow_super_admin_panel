<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('env_variables', function (Blueprint $table) {
            $table->text('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('env_variables', function (Blueprint $table) {
            $table->text('value')->nullable(false)->change();
        });
    }
};
