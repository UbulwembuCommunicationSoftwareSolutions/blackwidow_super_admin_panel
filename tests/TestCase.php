<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    protected function seedRolesAndPermissions(): void
    {
        // Create roles
        Role::create(['name' => 'super_admin']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'customer_manager']);
        Role::create(['name' => 'user']);

        // Create permissions
        Permission::create(['name' => 'view_customers']);
        Permission::create(['name' => 'create_customers']);
        Permission::create(['name' => 'edit_customers']);
        Permission::create(['name' => 'delete_customers']);

        Permission::create(['name' => 'view_customer_subscriptions']);
        Permission::create(['name' => 'create_customer_subscriptions']);
        Permission::create(['name' => 'edit_customer_subscriptions']);
        Permission::create(['name' => 'delete_customer_subscriptions']);

        Permission::create(['name' => 'view_users']);
        Permission::create(['name' => 'create_users']);
        Permission::create(['name' => 'edit_users']);
        Permission::create(['name' => 'delete_users']);

        Permission::create(['name' => 'view_subscription_types']);
        Permission::create(['name' => 'create_subscription_types']);
        Permission::create(['name' => 'edit_subscription_types']);
        Permission::create(['name' => 'delete_subscription_types']);

        // Assign permissions to roles
        $superAdmin = Role::findByName('super_admin');
        $superAdmin->givePermissionTo(Permission::all());

        $admin = Role::findByName('admin');
        $admin->givePermissionTo([
            'view_customers', 'create_customers', 'edit_customers', 'delete_customers',
            'view_customer_subscriptions', 'create_customer_subscriptions', 'edit_customer_subscriptions', 'delete_customer_subscriptions',
            'view_users', 'create_users', 'edit_users', 'delete_users',
            'view_subscription_types', 'create_subscription_types', 'edit_subscription_types', 'delete_subscription_types',
        ]);

        $customerManager = Role::findByName('customer_manager');
        $customerManager->givePermissionTo([
            'view_customers', 'create_customers', 'edit_customers', 'delete_customers',
            'view_customer_subscriptions', 'create_customer_subscriptions', 'edit_customer_subscriptions', 'delete_customer_subscriptions',
        ]);

        $user = Role::findByName('user');
        $user->givePermissionTo([
            'view_customers', 'view_customer_subscriptions',
        ]);
    }
}
