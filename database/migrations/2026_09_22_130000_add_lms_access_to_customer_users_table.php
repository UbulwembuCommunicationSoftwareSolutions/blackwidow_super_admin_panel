<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            $table->boolean('lms_access')->default(false)->after('stock_access');
        });
    }

    public function down(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            $table->dropColumn('lms_access');
        });
    }
};
