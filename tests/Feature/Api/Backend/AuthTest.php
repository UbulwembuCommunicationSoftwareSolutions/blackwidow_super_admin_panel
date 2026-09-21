<?php

use App\Models\Customer;
use App\Models\CustomerUser;
use App\Models\User;
use App\Support\CustomerAdminAccess;

it('rejects backend user without a token', function () {
    $this->getJson('/api/backend/user')->assertUnauthorized();
});

it('logs in an admin user and returns a backend token', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'secret-pass',
    ]);
    grantBackendPermissions($user);
    $user->assignRole('admin');

    $res = $this->postJson('/api/backend/login', [
        'email' => 'admin@example.com',
        'password' => 'secret-pass',
    ])->assertOk()
        ->assertJsonPath('data.user.email', 'admin@example.com')
        ->assertJsonPath('data.user.roles.0', 'admin')
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => ['id', 'name', 'email', 'roles', 'permissions'],
            ],
        ]);

    expect($res->json('data.token'))->toBeString()->not->toBeEmpty()
        ->and($res->json('data.user'))->not->toHaveKey('password');
});

it('rejects invalid login credentials', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'secret-pass',
    ]);

    $this->postJson('/api/backend/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('validates login fields', function () {
    $this->postJson('/api/backend/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('returns the current user with roles and permissions', function () {
    $user = actingAsBackendUser();
    $user->assignRole('admin');

    $this->getJson('/api/backend/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonFragment(['admin']);
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    grantBackendPermissions($user);
    $plainText = $user->createToken('backend', ['backend'])->plainTextToken;

    $this->withToken($plainText)
        ->postJson('/api/backend/logout')
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($user->tokens()->count())->toBe(0);
});

it('logs in a customer user who is a system admin', function () {
    $customer = Customer::factory()->create();
    CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'portal@example.com',
        'first_name' => 'Pat',
        'last_name' => 'Admin',
        'password' => 'secret-pass',
        'is_system_admin' => true,
        'skip_sync' => true,
    ]);

    $this->postJson('/api/backend/login', [
        'email' => 'portal@example.com',
        'password' => 'secret-pass',
    ])->assertOk()
        ->assertJsonPath('data.user.email', 'portal@example.com')
        ->assertJsonPath('data.user.name', 'Pat Admin')
        ->assertJsonPath('data.user.roles.0', 'customer_admin')
        ->assertJsonPath('data.user.actor_type', 'customer_admin')
        ->assertJsonPath('data.user.customer_id', $customer->id)
        ->assertJsonPath('data.user.permissions', CustomerAdminAccess::permissions());
});

it('rejects a customer user who is not a system admin', function () {
    $customer = Customer::factory()->create();
    CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'member@example.com',
        'password' => 'secret-pass',
        'is_system_admin' => false,
        'skip_sync' => true,
    ]);

    $this->postJson('/api/backend/login', [
        'email' => 'member@example.com',
        'password' => 'secret-pass',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('rejects a trashed system admin customer user', function () {
    $customer = Customer::factory()->create();
    $admin = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'gone@example.com',
        'password' => 'secret-pass',
        'is_system_admin' => true,
        'skip_sync' => true,
    ]);
    $admin->delete();

    $this->postJson('/api/backend/login', [
        'email' => 'gone@example.com',
        'password' => 'secret-pass',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('prefers a staff user when the email is shared with a customer admin', function () {
    $customer = Customer::factory()->create();
    User::factory()->create([
        'email' => 'shared@example.com',
        'password' => 'staff-pass',
    ]);
    CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'shared@example.com',
        'password' => 'customer-pass',
        'is_system_admin' => true,
        'skip_sync' => true,
    ]);

    $this->postJson('/api/backend/login', [
        'email' => 'shared@example.com',
        'password' => 'customer-pass',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    $this->postJson('/api/backend/login', [
        'email' => 'shared@example.com',
        'password' => 'staff-pass',
    ])->assertOk()
        ->assertJsonPath('data.user.email', 'shared@example.com')
        ->assertJsonMissingPath('data.user.actor_type');
});

it('signs in a customer admin on the customer portal when a staff user shares the email', function () {
    $customer = Customer::factory()->create();
    User::factory()->create([
        'email' => 'shared@example.com',
        'password' => 'staff-pass',
    ]);
    CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'shared@example.com',
        'first_name' => 'Pat',
        'last_name' => 'Admin',
        'password' => 'customer-pass',
        'is_system_admin' => true,
        'skip_sync' => true,
    ]);

    $this->postJson('/api/backend/login', [
        'email' => 'shared@example.com',
        'password' => 'customer-pass',
        'portal' => 'customer',
    ])->assertOk()
        ->assertJsonPath('data.user.actor_type', 'customer_admin')
        ->assertJsonPath('data.user.customer_id', $customer->id)
        ->assertJsonPath('data.user.name', 'Pat Admin');

    $this->postJson('/api/backend/login', [
        'email' => 'shared@example.com',
        'password' => 'staff-pass',
        'portal' => 'customer',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
