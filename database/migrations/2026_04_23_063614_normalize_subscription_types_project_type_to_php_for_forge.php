<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Forge Create Site expects project_type "php" or "html". Align legacy "Laravel" values.
     */
    public function up(): void
    {
        DB::table('subscription_types')
            ->whereRaw('LOWER(project_type) = ?', ['laravel'])
            ->update(['project_type' => 'php']);
    }

    public function down(): void
    {
        // Intentionally no automatic reverse: project_type may have been 'php' originally.
    }
};
