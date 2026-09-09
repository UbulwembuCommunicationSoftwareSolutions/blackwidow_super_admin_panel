<?php

use App\Models\User;

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
