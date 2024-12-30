<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            $table->boolean('console_access')->default(false);
            $table->boolean('firearm_access')->default(false);
            $table->boolean('responder_access')->default(false);
            $table->boolean('reporter_access')->default(false);
            $table->boolean('security_access')->default(false);
            $table->boolean('driver_access')->default(false);
            $table->boolean('survey_access')->default(false);
            $table->boolean('time_and_attendance_access')->default(false);
            $table->boolean('stock_access')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            //
        });
    }
};
