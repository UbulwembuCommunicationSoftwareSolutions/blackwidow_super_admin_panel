<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('subscription_type_id')->nullable();
            $table->string('handler');
            $table->timestamps();

            $table->index('subscription_type_id');
        });

        Schema::create('customer_dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('dashboard_id')->constrained('dashboards')->cascadeOnDelete();
            $table->foreignId('customer_subscription_id')->constrained('customer_subscriptions')->cascadeOnDelete();
            $table->string('reporting_database', 64);
            $table->string('database_host')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(
                ['customer_subscription_id', 'dashboard_id'],
                'customer_dashboards_sub_dash_unique'
            );
            $table->index('customer_id');
        });

        Schema::create('customer_user_customer_dashboards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_user_id');
            $table->unsignedBigInteger('customer_dashboard_id');
            $table->timestamps();

            $table->foreign('customer_user_id', 'cucd_customer_user_foreign')
                ->references('id')->on('customer_users')->cascadeOnDelete();
            $table->foreign('customer_dashboard_id', 'cucd_customer_dashboard_foreign')
                ->references('id')->on('customer_dashboards')->cascadeOnDelete();

            $table->unique(
                ['customer_user_id', 'customer_dashboard_id'],
                'cucd_user_dashboard_unique'
            );
        });

        DB::table('dashboards')->insert([
            'name' => 'AIMS Case Reporting',
            'slug' => 'aims-cases',
            'description' => 'Read-only case dashboard, case list and audit log for an AIMS (console) site.',
            'subscription_type_id' => 1,
            'handler' => 'aims_cases',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissions = [];
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'ForceDelete', 'RestoreAny', 'ForceDeleteAny'] as $action) {
            $permissions[] = Permission::findOrCreate($action.':Dashboard', 'web');
        }

        $superAdmin = Role::query()->where('name', 'super_admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_user_customer_dashboards');
        Schema::dropIfExists('customer_dashboards');
        Schema::dropIfExists('dashboards');

        DB::table('permissions')->where('guard_name', 'web')->where('name', 'like', '%:Dashboard')->delete();
    }
};
