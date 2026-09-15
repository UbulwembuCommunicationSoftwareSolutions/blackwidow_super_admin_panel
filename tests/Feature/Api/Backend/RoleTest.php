<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('rejects roles without a token', function () {
    $this->getJson('/api/backend/roles')->assertUnauthorized();
});

it('forbids roles without Shield permissions', function () {
    actingAsBackendForbidden();

    $this->getJson('/api/backend/roles')->assertForbidden();
});

it('allows the picker for anyone who can list admin users', function () {
    // Shield generates no *:Role permission, so ViewAny:User is what actually
    // gates this in every real environment.
    actingAsBackendUser(['ViewAny:User']);

    $this->getJson('/api/backend/roles')->assertOk();
});

it('returns roles as id and name pairs for the picker', function () {
    actingAsBackendUser();

    Role::findOrCreate('zz_last_role', 'web');
    Role::findOrCreate('aa_first_role', 'web');

    $response = $this->getJson('/api/backend/roles')->assertOk();
    $names = array_column($response->json('data'), 'name');

    expect(array_keys($response->json('data.0')))->toBe(['id', 'name'])
        ->and($names)->toContain('zz_last_role', 'aa_first_role')
        ->and($names)->toBe(collect($names)->sort()->values()->all());
});

it('returns a paginated roles index when page is present', function () {
    actingAsBackendUser();

    Role::findOrCreate('paginated_role_a', 'web');
    Role::findOrCreate('paginated_role_b', 'web');

    $response = $this->getJson('/api/backend/roles?page=1&per_page=15')->assertOk();

    expect($response->json('data'))->toBeArray()
        ->and($response->json('current_page'))->toBe(1)
        ->and($response->json('per_page'))->toBe(15)
        ->and(array_column($response->json('data'), 'name'))->toContain('paginated_role_a', 'paginated_role_b');

    expect($response->json('data.0'))->toHaveKeys([
        'id',
        'name',
        'permissions_count',
        'users_count',
        'is_super_admin',
    ]);
});

it('forbids the paginated roles index without ViewAny:Role', function () {
    actingAsBackendUser(['ViewAny:User']);

    $this->getJson('/api/backend/roles?page=1')->assertForbidden();
});

it('returns permissions grouped by model', function () {
    actingAsBackendUser();

    Permission::findOrCreate('ViewAny:Customer', 'web');
    Permission::findOrCreate('Create:Customer', 'web');
    Permission::findOrCreate('ViewAny:Role', 'web');

    $response = $this->getJson('/api/backend/permissions')->assertOk();
    $groups = collect($response->json('data'));

    expect($groups->pluck('model')->all())->toContain('Customer', 'Role');

    $customer = $groups->firstWhere('model', 'Customer');
    expect($customer['permissions'])->toContain('ViewAny:Customer', 'Create:Customer');
});

it('forbids the permissions endpoint without ViewAny:Role', function () {
    actingAsBackendUser(['ViewAny:User']);

    $this->getJson('/api/backend/permissions')->assertForbidden();
});

it('can create a role with synced permissions', function () {
    actingAsBackendUser();

    Permission::findOrCreate('ViewAny:Customer', 'web');
    Permission::findOrCreate('Update:Customer', 'web');

    $response = $this->postJson('/api/backend/roles', [
        'name' => 'ops_editor',
        'permissions' => ['ViewAny:Customer', 'Update:Customer'],
    ])->assertCreated();

    expect($response->json('data.name'))->toBe('ops_editor')
        ->and($response->json('data.permissions'))->toEqualCanonicalizing(['ViewAny:Customer', 'Update:Customer'])
        ->and($response->json('data.is_super_admin'))->toBeFalse();

    $role = Role::findByName('ops_editor', 'web');
    expect($role->permissions->pluck('name')->all())->toEqualCanonicalizing(['ViewAny:Customer', 'Update:Customer']);
});

it('can show update and delete a role and sync permissions', function () {
    actingAsBackendUser();

    Permission::findOrCreate('ViewAny:Customer', 'web');
    Permission::findOrCreate('Delete:Customer', 'web');
    $role = Role::findOrCreate('mutable_role', 'web');
    $role->syncPermissions(['ViewAny:Customer']);

    $this->getJson("/api/backend/roles/{$role->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'mutable_role')
        ->assertJsonPath('data.permissions.0', 'ViewAny:Customer');

    $this->putJson("/api/backend/roles/{$role->id}", [
        'name' => 'mutable_role_renamed',
        'permissions' => ['Delete:Customer'],
    ])->assertOk()
        ->assertJsonPath('data.name', 'mutable_role_renamed')
        ->assertJsonPath('data.permissions.0', 'Delete:Customer');

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['Delete:Customer']);

    $this->deleteJson("/api/backend/roles/{$role->id}")
        ->assertOk()
        ->assertJsonPath('ok', true);

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});

it('guards the super_admin role from rename and delete', function () {
    actingAsBackendUser();

    $superName = (string) config('filament-shield.super_admin.name', 'super_admin');
    $role = Role::findOrCreate($superName, 'web');
    Permission::findOrCreate('ViewAny:Customer', 'web');

    $this->putJson("/api/backend/roles/{$role->id}", [
        'name' => 'not_super_admin',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->putJson("/api/backend/roles/{$role->id}", [
        'permissions' => ['ViewAny:Customer'],
    ])->assertOk()
        ->assertJsonPath('data.name', $superName)
        ->assertJsonPath('data.is_super_admin', true);

    expect($role->fresh()->name)->toBe($superName)
        ->and($role->fresh()->permissions->pluck('name')->all())->toContain('ViewAny:Customer');

    $this->deleteJson("/api/backend/roles/{$role->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => $superName]);
});

it('validates role create and update payloads', function () {
    actingAsBackendUser();

    $this->postJson('/api/backend/roles', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->postJson('/api/backend/roles', [
        'name' => 'bad_perms',
        'permissions' => ['NotARealPermission'],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['permissions.0']);
});
