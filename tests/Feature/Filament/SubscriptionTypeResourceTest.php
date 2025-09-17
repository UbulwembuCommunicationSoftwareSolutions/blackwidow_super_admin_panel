<?php

use App\Filament\Resources\SubscriptionTypes\SubscriptionTypeResource;
use App\Models\SubscriptionType;
use App\Models\User;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Filters\TrashedFilter;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can list subscription types', function () {
    $subscriptionTypes = SubscriptionType::factory()->count(3)->create();

    Livewire::test(SubscriptionTypeResource\Pages\ListSubscriptionTypes::class)
        ->assertCanSeeTableRecords($subscriptionTypes);
});

it('can create a subscription type', function () {
    $newData = SubscriptionType::factory()->make();

    Livewire::test(SubscriptionTypeResource\Pages\CreateSubscriptionType::class)
        ->fillForm([
            'name' => $newData->name,
            'github_repo' => $newData->github_repo,
            'branch' => $newData->branch,
            'project_type' => $newData->project_type,
            'master_version' => $newData->master_version,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('subscription_types', [
        'name' => $newData->name,
        'github_repo' => $newData->github_repo,
        'branch' => $newData->branch,
        'project_type' => $newData->project_type,
        'master_version' => $newData->master_version,
    ]);
});

it('can edit a subscription type', function () {
    $subscriptionType = SubscriptionType::factory()->create();
    $newData = SubscriptionType::factory()->make();

    Livewire::test(SubscriptionTypeResource\Pages\EditSubscriptionType::class, [
        'record' => $subscriptionType->getRouteKey(),
    ])
        ->fillForm([
            'name' => $newData->name,
            'github_repo' => $newData->github_repo,
            'branch' => $newData->branch,
            'project_type' => $newData->project_type,
            'master_version' => $newData->master_version,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($subscriptionType->fresh())
        ->name->toBe($newData->name)
        ->github_repo->toBe($newData->github_repo)
        ->branch->toBe($newData->branch)
        ->project_type->toBe($newData->project_type)
        ->master_version->toBe($newData->master_version);
});

it('can delete a subscription type', function () {
    $subscriptionType = SubscriptionType::factory()->create();

    Livewire::test(SubscriptionTypeResource\Pages\ListSubscriptionTypes::class)
        ->callTableAction(DeleteAction::class, $subscriptionType);

    $this->assertSoftDeleted($subscriptionType);
});

it('can restore a subscription type', function () {
    $subscriptionType = SubscriptionType::factory()->create();
    $subscriptionType->delete();

    Livewire::test(SubscriptionTypeResource\Pages\ListSubscriptionTypes::class)
        ->callTableAction(RestoreAction::class, $subscriptionType);

    expect($subscriptionType->fresh())->deleted_at->toBeNull();
});

it('can force delete a subscription type', function () {
    $subscriptionType = SubscriptionType::factory()->create();
    $subscriptionType->delete();

    Livewire::test(SubscriptionTypeResource\Pages\ListSubscriptionTypes::class)
        ->callTableAction(ForceDeleteAction::class, $subscriptionType);

    $this->assertDatabaseMissing('subscription_types', [
        'id' => $subscriptionType->id,
    ]);
});

it('can view subscription type details', function () {
    $subscriptionType = SubscriptionType::factory()->create();

    Livewire::test(SubscriptionTypeResource\Pages\ViewSubscriptionType::class, [
        'record' => $subscriptionType->getRouteKey(),
    ])
        ->assertFormSet([
            'name' => $subscriptionType->name,
            'github_repo' => $subscriptionType->github_repo,
            'branch' => $subscriptionType->branch,
            'project_type' => $subscriptionType->project_type,
            'master_version' => $subscriptionType->master_version,
        ]);
});

it('validates required fields when creating subscription type', function () {
    Livewire::test(SubscriptionTypeResource\Pages\CreateSubscriptionType::class)
        ->fillForm([
            'name' => '',
            'github_repo' => '',
            'branch' => '',
            'project_type' => '',
            'master_version' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('can search subscription types by name', function () {
    $subscriptionType1 = SubscriptionType::factory()->create(['name' => 'Laravel App']);
    $subscriptionType2 = SubscriptionType::factory()->create(['name' => 'React App']);

    Livewire::test(SubscriptionTypeResource\Pages\ListSubscriptionTypes::class)
        ->searchTable('Laravel')
        ->assertCanSeeTableRecords([$subscriptionType1])
        ->assertCanNotSeeTableRecords([$subscriptionType2]);
});

it('can search subscription types by github repo', function () {
    $subscriptionType1 = SubscriptionType::factory()->create(['github_repo' => 'laravel/laravel']);
    $subscriptionType2 = SubscriptionType::factory()->create(['github_repo' => 'facebook/react']);

    Livewire::test(SubscriptionTypeResource\Pages\ListSubscriptionTypes::class)
        ->searchTable('laravel')
        ->assertCanSeeTableRecords([$subscriptionType1])
        ->assertCanNotSeeTableRecords([$subscriptionType2]);
});

it('can filter subscription types by project type', function () {
    $laravelType = SubscriptionType::factory()->create(['project_type' => 'Laravel']);
    $reactType = SubscriptionType::factory()->create(['project_type' => 'React']);

    Livewire::test(SubscriptionTypeResource\Pages\ListSubscriptionTypes::class)
        ->filterTable('project_type', 'Laravel')
        ->assertCanSeeTableRecords([$laravelType])
        ->assertCanNotSeeTableRecords([$reactType]);
});

it('can filter subscription types by trashed status', function () {
    $activeType = SubscriptionType::factory()->create();
    $deletedType = SubscriptionType::factory()->create();
    $deletedType->delete();

    Livewire::test(SubscriptionTypeResource\Pages\ListSubscriptionTypes::class)
        ->filterTable(TrashedFilter::class, 'trashed')
        ->assertCanSeeTableRecords([$deletedType])
        ->assertCanNotSeeTableRecords([$activeType]);
});

it('shows all subscription type fields in table', function () {
    $subscriptionType = SubscriptionType::factory()->create();

    Livewire::test(SubscriptionTypeResource\Pages\ListSubscriptionTypes::class)
        ->assertCanSeeTableRecords([$subscriptionType])
        ->assertTableColumnExists('id')
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('github_repo')
        ->assertTableColumnExists('branch')
        ->assertTableColumnExists('project_type')
        ->assertTableColumnExists('master_version');
});

it('can sort subscription types by name', function () {
    $typeA = SubscriptionType::factory()->create(['name' => 'Type A']);
    $typeB = SubscriptionType::factory()->create(['name' => 'Type B']);
    $typeC = SubscriptionType::factory()->create(['name' => 'Type C']);

    Livewire::test(SubscriptionTypeResource\Pages\ListSubscriptionTypes::class)
        ->sortTable('name', 'desc')
        ->assertCanSeeTableRecords([$typeC, $typeB, $typeA]);
});

it('validates unique subscription type name', function () {
    $existingType = SubscriptionType::factory()->create(['name' => 'Unique Type']);

    Livewire::test(SubscriptionTypeResource\Pages\CreateSubscriptionType::class)
        ->fillForm([
            'name' => 'Unique Type',
            'github_repo' => 'test/repo',
            'branch' => 'main',
            'project_type' => 'Laravel',
            'master_version' => '1.0.0',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

it('can bulk delete subscription types', function () {
    $type1 = SubscriptionType::factory()->create();
    $type2 = SubscriptionType::factory()->create();

    Livewire::test(SubscriptionTypeResource\Pages\ListSubscriptionTypes::class)
        ->callTableBulkAction('delete', [$type1, $type2]);

    $this->assertSoftDeleted($type1);
    $this->assertSoftDeleted($type2);
});

it('can bulk restore subscription types', function () {
    $type1 = SubscriptionType::factory()->create();
    $type2 = SubscriptionType::factory()->create();

    $type1->delete();
    $type2->delete();

    Livewire::test(SubscriptionTypeResource\Pages\ListSubscriptionTypes::class)
        ->filterTable(TrashedFilter::class, 'trashed')
        ->callTableBulkAction('restore', [$type1, $type2]);

    expect($type1->fresh())->deleted_at->toBeNull();
    expect($type2->fresh())->deleted_at->toBeNull();
});
