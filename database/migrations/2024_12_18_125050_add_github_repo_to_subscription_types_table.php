<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscription_types', function (Blueprint $table) {
            $table->string('github_repo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_types', function (Blueprint $table) {
            $table->dropColumn('github_repo');
        });
    }
};
