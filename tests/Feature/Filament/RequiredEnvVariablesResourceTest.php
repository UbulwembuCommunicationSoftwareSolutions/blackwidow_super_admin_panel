<?php

use App\Filament\Resources\RequiredEnvVariables\RequiredEnvVariablesResource;
use App\Models\RequiredEnvVariables;
use App\Models\SubscriptionType;
use App\Models\User;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can list required env variables', function () {
    $envVars = RequiredEnvVariables::factory()->count(3)->create();

    Livewire::test(RequiredEnvVariablesResource\Pages\ListRequiredEnvVariables::class)
        ->assertCanSeeTableRecords($envVars);
});

it('can create a required env variable', function () {
    $subscriptionType = SubscriptionType::factory()->create();
    $newData = RequiredEnvVariables::factory()->make([
        'subscription_type_id' => $subscriptionType->id,
    ]);

    Livewire::test(RequiredEnvVariablesResource\Pages\CreateRequiredEnvVariables::class)
        ->fillForm([
            'key' => $newData->key,
            'value' => $newData->value,
            'subscription_type_id' => $subscriptionType->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('required_env_variables', [
        'key' => $newData->key,
        'value' => $newData->value,
        'subscription_type_id' => $subscriptionType->id,
    ]);
});

it('can edit a required env variable', function () {
    $envVar = RequiredEnvVariables::factory()->create();
    $newData = RequiredEnvVariables::factory()->make();

    Livewire::test(RequiredEnvVariablesResource\Pages\EditRequiredEnvVariables::class, [
        'record' => $envVar->getRouteKey(),
    ])
        ->fillForm([
            'key' => $newData->key,
            'value' => $newData->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($envVar->fresh())
        ->key->toBe($newData->key)
        ->value->toBe($newData->value);
});

it('can delete a required env variable', function () {
    $envVar = RequiredEnvVariables::factory()->create();

    Livewire::test(RequiredEnvVariablesResource\Pages\ListRequiredEnvVariables::class)
        ->callTableAction(DeleteAction::class, $envVar);

    $this->assertSoftDeleted($envVar);
});

it('can view required env variable details', function () {
    $envVar = RequiredEnvVariables::factory()->create();

    Livewire::test(RequiredEnvVariablesResource\Pages\ViewRequiredEnvVariables::class, [
        'record' => $envVar->getRouteKey(),
    ])
        ->assertFormSet([
            'key' => $envVar->key,
            'value' => $envVar->value,
        ]);
});

it('validates required fields when creating env variable', function () {
    Livewire::test(RequiredEnvVariablesResource\Pages\CreateRequiredEnvVariables::class)
        ->fillForm([
            'key' => '',
            'value' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['key' => 'required', 'value' => 'required']);
});

it('shows subscription type relationship', function () {
    $subscriptionType = SubscriptionType::factory()->create(['name' => 'Laravel App']);
    $envVar = RequiredEnvVariables::factory()->create([
        'subscription_type_id' => $subscriptionType->id,
    ]);

    Livewire::test(RequiredEnvVariablesResource\Pages\ListRequiredEnvVariables::class)
        ->assertCanSeeTableRecords([$envVar])
        ->assertTableColumnExists('subscriptionType.name');
});

it('can filter env variables by subscription type', function () {
    $subscriptionType1 = SubscriptionType::factory()->create(['name' => 'Laravel App']);
    $subscriptionType2 = SubscriptionType::factory()->create(['name' => 'React App']);

    $envVar1 = RequiredEnvVariables::factory()->create(['subscription_type_id' => $subscriptionType1->id]);
    $envVar2 = RequiredEnvVariables::factory()->create(['subscription_type_id' => $subscriptionType2->id]);

    Livewire::test(RequiredEnvVariablesResource\Pages\ListRequiredEnvVariables::class)
        ->filterTable('subscriptionType', $subscriptionType1->id)
        ->assertCanSeeTableRecords([$envVar1])
        ->assertCanNotSeeTableRecords([$envVar2]);
});

it('can search env variables by key', function () {
    $envVar1 = RequiredEnvVariables::factory()->create(['key' => 'APP_NAME']);
    $envVar2 = RequiredEnvVariables::factory()->create(['key' => 'DB_HOST']);

    Livewire::test(RequiredEnvVariablesResource\Pages\ListRequiredEnvVariables::class)
        ->searchTable('APP_NAME')
        ->assertCanSeeTableRecords([$envVar1])
        ->assertCanNotSeeTableRecords([$envVar2]);
});
