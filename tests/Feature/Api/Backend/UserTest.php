<?php

use App\Models\User;

it('rejects users without a token', function () {
    $this->getJson('/api/backend/users')->assertUnauthorized();
});

it('forbids users without Shield permissions', function () {
    actingAsBackendForbidden();

    $this->getJson('/api/backend/users')->assertForbidden();
});

it('returns 404 for a missing user', function () {
    actingAsBackendUser();

    $this->getJson('/api/backend/users/999999')->assertNotFound();
});

it('validates user create', function () {
    actingAsBackendUser();

    $this->postJson('/api/backend/users', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('can crud an admin user and sync roles without returning the password', function () {
    actingAsBackendUser();

    $create = $this->postJson('/api/backend/users', [
        'name' => 'Pat Admin',
        'email' => 'pat@example.com',
        'password' => 'secret1',
        'roles' => ['admin'],
    ])->assertCreated();

    $id = $create->json('data.id');
    expect($create->json('data.email'))->toBe('pat@example.com')
        ->and($create->json('data'))->not->toHaveKey('password')
        ->and(collect($create->json('data.roles'))->pluck('name')->all())->toContain('admin');

    $this->getJson('/api/backend/users?per_page=25')->assertOk();

    $this->putJson("/api/backend/users/{$id}", [
        'name' => 'Pat Updated',
        'roles' => ['user'],
    ])->assertOk()->assertJsonPath('data.name', 'Pat Updated');

    $fresh = User::query()->find($id);
    expect($fresh->hasRole('user'))->toBeTrue()
        ->and($fresh->hasRole('admin'))->toBeFalse();

    $this->deleteJson("/api/backend/users/{$id}")
        ->assertOk()
        ->assertJsonPath('ok', true);

    $this->assertDatabaseMissing('users', ['id' => $id]);
});
