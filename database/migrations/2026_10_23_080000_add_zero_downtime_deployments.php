<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployment_templates', function (Blueprint $table) {
            // Forge zero-downtime (release based) deployment script. Null = the type is still created as a classic site.
            $table->longText('native_script')->nullable()->after('script');
        });

        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->boolean('zero_downtime')->default(false);
        });

        // Sites already converted to Forge zero-downtime deployments carry a native script.
        DB::table('customer_subscriptions')
            ->whereIn('id', DB::table('deployment_scripts')
                ->where('script', 'like', '%$CREATE_RELEASE()%')
                ->select('customer_subscription_id'))
            ->update(['zero_downtime' => true]);
    }

    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropColumn('zero_downtime');
        });

        Schema::table('deployment_templates', function (Blueprint $table) {
            $table->dropColumn('native_script');
        });
    }
};
