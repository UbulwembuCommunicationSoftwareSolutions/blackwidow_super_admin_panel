<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
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

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = collect(self::ACTIONS)
            ->map(fn (string $action) => Permission::findOrCreate($action.':Role', 'web'));

        $superAdminName = config('filament-shield.super_admin.name', 'super_admin');
        $superAdmin = Role::query()
            ->where('name', $superAdminName)
            ->where('guard_name', 'web')
            ->first();

        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions->all());
        }
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::ACTIONS as $action) {
            Permission::query()
                ->where('name', $action.':Role')
                ->where('guard_name', 'web')
                ->delete();
        }
    }
};
