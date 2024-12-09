<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use BezhanSalleh\FilamentShield\Support\Utils;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolesWithPermissions = '[{"name":"super_admin","guard_name":"web","permissions":["view_customer","view_any_customer","create_customer","update_customer","restore_customer","restore_any_customer","replicate_customer","reorder_customer","delete_customer","delete_any_customer","force_delete_customer","force_delete_any_customer","view_customer::subscription","view_any_customer::subscription","create_customer::subscription","update_customer::subscription","restore_customer::subscription","restore_any_customer::subscription","replicate_customer::subscription","reorder_customer::subscription","delete_customer::subscription","delete_any_customer::subscription","force_delete_customer::subscription","force_delete_any_customer::subscription","view_customer::user","view_any_customer::user","create_customer::user","update_customer::user","restore_customer::user","restore_any_customer::user","replicate_customer::user","reorder_customer::user","delete_customer::user","delete_any_customer::user","force_delete_customer::user","force_delete_any_customer::user","view_deployment::script","view_any_deployment::script","create_deployment::script","update_deployment::script","restore_deployment::script","restore_any_deployment::script","replicate_deployment::script","reorder_deployment::script","delete_deployment::script","delete_any_deployment::script","force_delete_deployment::script","force_delete_any_deployment::script","view_env::variables","view_any_env::variables","create_env::variables","update_env::variables","restore_env::variables","restore_any_env::variables","replicate_env::variables","reorder_env::variables","delete_env::variables","delete_any_env::variables","force_delete_env::variables","force_delete_any_env::variables","view_required::env::variables","view_any_required::env::variables","create_required::env::variables","update_required::env::variables","restore_required::env::variables","restore_any_required::env::variables","replicate_required::env::variables","reorder_required::env::variables","delete_required::env::variables","delete_any_required::env::variables","force_delete_required::env::variables","force_delete_any_required::env::variables","view_role","view_any_role","create_role","update_role","delete_role","delete_any_role","view_subscription::type","view_any_subscription::type","create_subscription::type","update_subscription::type","restore_subscription::type","restore_any_subscription::type","replicate_subscription::type","reorder_subscription::type","delete_subscription::type","delete_any_subscription::type","force_delete_subscription::type","force_delete_any_subscription::type"]}]';
        $directPermissions = '[]';

        static::makeRolesWithPermissions($rolesWithPermissions);
        static::makeDirectPermissions($directPermissions);

        $this->command->info('Shield Seeding Completed.');
    }

    protected static function makeRolesWithPermissions(string $rolesWithPermissions): void
    {
        if (! blank($rolePlusPermissions = json_decode($rolesWithPermissions, true))) {
            /** @var Model $roleModel */
            $roleModel = Utils::getRoleModel();
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($rolePlusPermissions as $rolePlusPermission) {
                $role = $roleModel::firstOrCreate([
                    'name' => $rolePlusPermission['name'],
                    'guard_name' => $rolePlusPermission['guard_name'],
                ]);

                if (! blank($rolePlusPermission['permissions'])) {
                    $permissionModels = collect($rolePlusPermission['permissions'])
                        ->map(fn ($permission) => $permissionModel::firstOrCreate([
                            'name' => $permission,
                            'guard_name' => $rolePlusPermission['guard_name'],
                        ]))
                        ->all();

                    $role->syncPermissions($permissionModels);
                }
            }
        }
    }

    public static function makeDirectPermissions(string $directPermissions): void
    {
        if (! blank($permissions = json_decode($directPermissions, true))) {
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($permissions as $permission) {
                if ($permissionModel::whereName($permission)->doesntExist()) {
                    $permissionModel::create([
                        'name' => $permission['name'],
                        'guard_name' => $permission['guard_name'],
                    ]);
                }
            }
        }
    }
}
