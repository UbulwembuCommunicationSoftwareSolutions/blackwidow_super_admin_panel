<?php

use App\Filament\Resources\RequiredEnvVariables\Pages\CreateRequiredEnvVariables;
use App\Filament\Resources\RequiredEnvVariables\Pages\EditRequiredEnvVariables;
use App\Filament\Resources\RequiredEnvVariables\Pages\ListRequiredEnvVariables;
use App\Models\RequiredEnvVariables;
use App\Models\SubscriptionType;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $user = User::factory()->create();
    foreach ([
        'ViewAny:RequiredEnvVariables',
        'View:RequiredEnvVariables',
        'Create:RequiredEnvVariables',
        'Update:RequiredEnvVariables',
        'Delete:RequiredEnvVariables',
    ] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    $user->givePermissionTo([
        'ViewAny:RequiredEnvVariables',
        'View:RequiredEnvVariables',
        'Create:RequiredEnvVariables',
        'Update:RequiredEnvVariables',
        'Delete:RequiredEnvVariables',
    ]);
    $this->actingAs($user);
});

it('can list required env variables', function () {
    RequiredEnvVariables::factory()->count(3)->create();

    $this->assertDatabaseCount('required_env_variables', 3);

    Livewire::test(ListRequiredEnvVariables::class)
        ->loadTable()
        ->assertSuccessful();
});

it('can create a required env variable', function () {
    $subscriptionType = SubscriptionType::factory()->create();
    $newData = RequiredEnvVariables::factory()->make([
        'subscription_type_id' => $subscriptionType->id,
    ]);

    Livewire::test(CreateRequiredEnvVariables::class)
        ->fillForm([
            'key' => $newData->key,
            'value' => $newData->value,
            'requires_manual_fill' => false,
            'subscription_type_id' => $subscriptionType->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('required_env_variables', [
        'key' => $newData->key,
        'value' => $newData->value,
        'subscription_type_id' => $subscriptionType->id,
        'requires_manual_fill' => false,
    ]);
});

it('can edit a required env variable', function () {
    $envVar = RequiredEnvVariables::factory()->create();
    $newData = RequiredEnvVariables::factory()->make();

    Livewire::test(EditRequiredEnvVariables::class, [
        'record' => $envVar->getRouteKey(),
    ])
        ->fillForm([
            'key' => $newData->key,
            'value' => $newData->value,
            'requires_manual_fill' => $newData->requires_manual_fill,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($envVar->fresh())
        ->key->toBe($newData->key)
        ->value->toBe($newData->value);
});

it('deletes required env variable rows', function () {
    $envVar = RequiredEnvVariables::factory()->create();
    $id = $envVar->id;

    $envVar->delete();

    $this->assertDatabaseMissing('required_env_variables', [
        'id' => $id,
    ]);
});

it('validates required fields when creating env variable', function () {
    Livewire::test(CreateRequiredEnvVariables::class)
        ->fillForm([
            'key' => '',
            'value' => '',
            'requires_manual_fill' => false,
        ])
        ->call('create')
        ->assertHasFormErrors(['key' => 'required', 'value' => 'required']);
});

it('shows subscription type relationship', function () {
    $subscriptionType = SubscriptionType::factory()->create(['name' => 'Laravel App']);
    RequiredEnvVariables::factory()->create([
        'subscription_type_id' => $subscriptionType->id,
    ]);

    $this->assertDatabaseCount('required_env_variables', 1);

    Livewire::test(ListRequiredEnvVariables::class)
        ->loadTable()
        ->assertTableColumnExists('subscriptionType.name')
        ->assertSuccessful();
});

it('can filter env variables by subscription type', function () {
    $subscriptionType1 = SubscriptionType::factory()->create(['name' => 'Laravel App']);
    $subscriptionType2 = SubscriptionType::factory()->create(['name' => 'React App']);

    $envVar1 = RequiredEnvVariables::factory()->create(['subscription_type_id' => $subscriptionType1->id]);
    $envVar2 = RequiredEnvVariables::factory()->create(['subscription_type_id' => $subscriptionType2->id]);

    Livewire::test(ListRequiredEnvVariables::class)
        ->loadTable()
        ->filterTable('subscriptionType', [
            'subscriptionType' => $subscriptionType1->id,
        ])
        ->assertCanSeeTableRecords([$envVar1])
        ->assertCanNotSeeTableRecords([$envVar2]);
});

it('can search env variables by key', function () {
    RequiredEnvVariables::factory()->create(['key' => 'APP_NAME']);
    RequiredEnvVariables::factory()->create(['key' => 'DB_HOST']);

    $this->assertDatabaseCount('required_env_variables', 2);

    Livewire::test(ListRequiredEnvVariables::class)
        ->loadTable()
        ->searchTable('APP_NAME')
        ->assertSuccessful();
});
