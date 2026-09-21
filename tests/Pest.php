<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    TestCase::class,
)->in('Feature');

uses(
    TestCase::class,
)->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeSoftDeleted', function () {
    return $this->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createUserWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function createCustomerWithSubscriptions(int $count = 1): Customer
{
    $customer = Customer::factory()->create();
    $subscriptionType = SubscriptionType::factory()->create();

    for ($i = 0; $i < $count; $i++) {
        CustomerSubscription::factory()->create([
            'customer_id' => $customer->id,
            'subscription_type_id' => $subscriptionType->id,
        ]);
    }

    return $customer;
}

function backendShieldPermissions(): array
{
    $resources = [
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
    $actions = [
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

    $permissions = [];
    foreach ($resources as $resource) {
        foreach ($actions as $action) {
            $permissions[] = $action.':'.$resource;
        }
    }

    return $permissions;
}

function grantBackendPermissions(User $user, ?array $permissions = null): User
{
    $permissions ??= backendShieldPermissions();
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user->givePermissionTo($permissions);
    $user->forgetCachedPermissions();

    return $user;
}

function actingAsBackendUser(?array $permissions = null): User
{
    $user = grantBackendPermissions(User::factory()->create(), $permissions);
    Sanctum::actingAs($user, ['backend']);

    return $user;
}

function actingAsBackendForbidden(): User
{
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['backend']);

    return $user;
}

function actingAsCustomerAdmin(?Customer $customer = null, array $attributes = []): CustomerUser
{
    $customer ??= Customer::factory()->create();
    $password = $attributes['password'] ?? 'secret-pass';
    unset($attributes['password']);

    $user = CustomerUser::factory()->create(array_merge([
        'customer_id' => $customer->id,
        'is_system_admin' => true,
        'skip_sync' => true,
        'password' => $password,
    ], $attributes));

    Sanctum::actingAs($user, ['backend']);

    return $user;
}
