<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('level_one_in_use')->default(true);
            $table->boolean('level_two_in_use')->default(true);
            $table->boolean('level_three_in_use')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['level_one_in_use', 'level_two_in_use', 'level_three_in_use']);
        });
    }
};
