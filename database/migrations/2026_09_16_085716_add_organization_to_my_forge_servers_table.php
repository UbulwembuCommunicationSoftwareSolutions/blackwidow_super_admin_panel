<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('my_forge_servers', function (Blueprint $table) {
            $table->string('organization')->nullable()->after('forge_server_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('my_forge_servers', function (Blueprint $table) {
            $table->dropColumn('organization');
        });
    }
};
