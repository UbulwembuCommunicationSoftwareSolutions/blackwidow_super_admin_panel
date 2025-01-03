<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('level_one_description')->default('Level 1');
            $table->string('level_two_description')->default('Level 2');
            $table->string('level_three_description')->default('Level 3');
            $table->string('level_four_description')->default('Level 4');
            $table->string('level_five_description')->default('Level 5');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['level_one_description', 'level_two_description', 'level_three_description', 'level_four_description', 'level_five_description']);
        });
    }
};
