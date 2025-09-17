<?php

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can list users', function () {
    $users = User::factory()->count(3)->create();

    Livewire::test(UserResource\Pages\ListUsers::class)
        ->assertCanSeeTableRecords($users);
});

it('can create a user', function () {
    $role = Role::create(['name' => 'test-role']);
    $newData = User::factory()->make();

    Livewire::test(UserResource\Pages\CreateUser::class)
        ->fillForm([
            'name' => $newData->name,
            'email' => $newData->email,
            'password' => 'password123',
            'roles' => [$role->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', [
        'name' => $newData->name,
        'email' => $newData->email,
    ]);

    $user = User::where('email', $newData->email)->first();
    expect($user->hasRole('test-role'))->toBeTrue();
});

it('can edit a user', function () {
    $user = User::factory()->create();
    $newData = User::factory()->make();

    Livewire::test(UserResource\Pages\EditUser::class, [
        'record' => $user->getRouteKey(),
    ])
        ->fillForm([
            'name' => $newData->name,
            'email' => $newData->email,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh())
        ->name->toBe($newData->name)
        ->email->toBe($newData->email);
});

it('can delete a user', function () {
    $user = User::factory()->create();

    Livewire::test(UserResource\Pages\ListUsers::class)
        ->callTableAction('delete', $user);

    $this->assertSoftDeleted($user);
});

it('can view user details', function () {
    $user = User::factory()->create();

    Livewire::test(UserResource\Pages\ViewUser::class, [
        'record' => $user->getRouteKey(),
    ])
        ->assertFormSet([
            'name' => $user->name,
            'email' => $user->email,
        ]);
});

it('validates required fields when creating user', function () {
    Livewire::test(UserResource\Pages\CreateUser::class)
        ->fillForm([
            'name' => '',
            'email' => '',
            'password' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'required', 'password' => 'required']);
});

it('validates email format', function () {
    Livewire::test(UserResource\Pages\CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password123',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'email']);
});

it('validates unique email', function () {
    $existingUser = User::factory()->create(['email' => 'test@example.com']);

    Livewire::test(UserResource\Pages\CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

it('can assign multiple roles to user', function () {
    $role1 = Role::create(['name' => 'role-1']);
    $role2 = Role::create(['name' => 'role-2']);
    $newData = User::factory()->make();

    Livewire::test(UserResource\Pages\CreateUser::class)
        ->fillForm([
            'name' => $newData->name,
            'email' => $newData->email,
            'password' => 'password123',
            'roles' => [$role1->id, $role2->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', $newData->email)->first();
    expect($user->hasRole('role-1'))->toBeTrue();
    expect($user->hasRole('role-2'))->toBeTrue();
});

it('can update user roles', function () {
    $user = User::factory()->create();
    $role1 = Role::create(['name' => 'role-1']);
    $role2 = Role::create(['name' => 'role-2']);

    $user->assignRole($role1);

    Livewire::test(UserResource\Pages\EditUser::class, [
        'record' => $user->getRouteKey(),
    ])
        ->fillForm(['roles' => [$role2->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    expect($user->hasRole('role-1'))->toBeFalse();
    expect($user->hasRole('role-2'))->toBeTrue();
});

it('can search users by name', function () {
    $user1 = User::factory()->create(['name' => 'John Doe']);
    $user2 = User::factory()->create(['name' => 'Jane Smith']);

    Livewire::test(UserResource\Pages\ListUsers::class)
        ->searchTable('John')
        ->assertCanSeeTableRecords([$user1])
        ->assertCanNotSeeTableRecords([$user2]);
});

it('can search users by email', function () {
    $user1 = User::factory()->create(['email' => 'john@example.com']);
    $user2 = User::factory()->create(['email' => 'jane@example.com']);

    Livewire::test(UserResource\Pages\ListUsers::class)
        ->searchTable('john@example.com')
        ->assertCanSeeTableRecords([$user1])
        ->assertCanNotSeeTableRecords([$user2]);
});

it('shows user roles in table', function () {
    $role = Role::create(['name' => 'admin']);
    $user = User::factory()->create();
    $user->assignRole($role);

    Livewire::test(UserResource\Pages\ListUsers::class)
        ->assertCanSeeTableRecords([$user])
        ->assertTableColumnExists('roles');
});

it('can set email verification date', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    Livewire::test(UserResource\Pages\EditUser::class, [
        'record' => $user->getRouteKey(),
    ])
        ->fillForm(['email_verified_at' => now()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh())->email_verified_at->not->toBeNull();
});

it('validates password minimum length', function () {
    Livewire::test(UserResource\Pages\CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => '123',
        ])
        ->call('create')
        ->assertHasFormErrors(['password' => 'min']);
});

it('can filter users by role', function () {
    $adminRole = Role::create(['name' => 'admin']);
    $userRole = Role::create(['name' => 'user']);

    $adminUser = User::factory()->create();
    $adminUser->assignRole($adminRole);

    $regularUser = User::factory()->create();
    $regularUser->assignRole($userRole);

    Livewire::test(UserResource\Pages\ListUsers::class)
        ->filterTable('roles', $adminRole->id)
        ->assertCanSeeTableRecords([$adminUser])
        ->assertCanNotSeeTableRecords([$regularUser]);
});
