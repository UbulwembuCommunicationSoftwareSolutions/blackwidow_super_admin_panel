<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('required_env_variables', function (Blueprint $table) {
            $table->dropColumn('default_value');
        });
    }

    public function down(): void
    {
        Schema::table('required_env_variables', function (Blueprint $table) {
            //
        });
    }
};
