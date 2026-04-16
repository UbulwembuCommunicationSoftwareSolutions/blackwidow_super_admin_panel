<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('required_env_variables', function (Blueprint $table) {
            $table->boolean('requires_manual_fill')->default(false)->after('value');
            $table->string('admin_label')->nullable()->after('requires_manual_fill');
            $table->text('help_text')->nullable()->after('admin_label');
        });
    }

    public function down(): void
    {
        Schema::table('required_env_variables', function (Blueprint $table) {
            $table->dropColumn(['requires_manual_fill', 'admin_label', 'help_text']);
        });
    }
};
