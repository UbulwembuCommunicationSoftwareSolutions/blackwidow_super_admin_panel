<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->timestamp('logo_1_updated_at')->nullable()->after('logo_5');
            $table->timestamp('logo_2_updated_at')->nullable()->after('logo_1_updated_at');
            $table->timestamp('logo_3_updated_at')->nullable()->after('logo_2_updated_at');
            $table->timestamp('logo_4_updated_at')->nullable()->after('logo_3_updated_at');
            $table->timestamp('logo_5_updated_at')->nullable()->after('logo_4_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'logo_1_updated_at',
                'logo_2_updated_at',
                'logo_3_updated_at',
                'logo_4_updated_at',
                'logo_5_updated_at',
            ]);
        });
    }
};
