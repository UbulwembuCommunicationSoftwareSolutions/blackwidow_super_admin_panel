<?php

use Spatie\Permission\Models\Role;

it('rejects roles without a token', function () {
    $this->getJson('/api/backend/roles')->assertUnauthorized();
});

it('forbids roles without Shield permissions', function () {
    actingAsBackendForbidden();

    $this->getJson('/api/backend/roles')->assertForbidden();
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
