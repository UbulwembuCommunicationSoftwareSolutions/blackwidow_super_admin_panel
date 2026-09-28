<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    /** @var list<string> */
    private const RESOURCES = [
        'Customer',
        'CustomerSubscription',
        'CustomerUser',
        'User',
        'UserCustomer',
        'DeploymentScript',
        'DeploymentTemplate',
        'EnvVariables',
        'TemplateEnvVariables',
        'ForgeServer',
        'NginxTemplate',
        'SubscriptionType',
        'Role',
    ];

    /** @var list<string> */
    private const ACTIONS = [
        'ViewAny',
        'View',
        'Create',
        'Update',
        'Delete',
        'Restore',
        'ForceDelete',
        'RestoreAny',
        'ForceDeleteAny',
    ];

    /**
     * Abilities outside the standard CRUD matrix.
     *
     * @var list<string>
     */
    private const EXTRA_PERMISSIONS = [
        'ManagePermissions:CustomerUser',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [];
        foreach (self::RESOURCES as $resource) {
            foreach (self::ACTIONS as $action) {
                $permissions[] = Permission::findOrCreate($action.':'.$resource, 'web');
            }
        }

        foreach (self::EXTRA_PERMISSIONS as $permission) {
            $permissions[] = Permission::findOrCreate($permission, 'web');
        }

        $superAdminName = config('filament-shield.super_admin.name', 'super_admin');
        $superAdmin = Role::firstOrCreate([
            'name' => $superAdminName,
            'guard_name' => 'web',
        ]);
        $superAdmin->syncPermissions($permissions);

        $this->command?->info('Shield Seeding Completed (Ability:Model).');
    }
}
