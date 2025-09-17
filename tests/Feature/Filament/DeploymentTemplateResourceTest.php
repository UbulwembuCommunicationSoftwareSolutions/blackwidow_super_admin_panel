<?php

use App\Filament\Resources\DeploymentTemplates\DeploymentTemplateResource;
use App\Models\DeploymentTemplate;
use App\Models\SubscriptionType;
use App\Models\User;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can list deployment templates', function () {
    $templates = DeploymentTemplate::factory()->count(3)->create();

    Livewire::test(DeploymentTemplateResource\Pages\ListDeploymentTemplates::class)
        ->assertCanSeeTableRecords($templates);
});

it('can create a deployment template', function () {
    $subscriptionType = SubscriptionType::factory()->create();
    $newData = DeploymentTemplate::factory()->make([
        'subscription_type_id' => $subscriptionType->id,
    ]);

    Livewire::test(DeploymentTemplateResource\Pages\CreateDeploymentTemplate::class)
        ->fillForm([
            'script' => $newData->script,
            'subscription_type_id' => $subscriptionType->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('deployment_templates', [
        'script' => $newData->script,
        'subscription_type_id' => $subscriptionType->id,
    ]);
});

it('can edit a deployment template', function () {
    $template = DeploymentTemplate::factory()->create();
    $newData = DeploymentTemplate::factory()->make();

    Livewire::test(DeploymentTemplateResource\Pages\EditDeploymentTemplate::class, [
        'record' => $template->getRouteKey(),
    ])
        ->fillForm([
            'script' => $newData->script,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($template->fresh())->script->toBe($newData->script);
});

it('can delete a deployment template', function () {
    $template = DeploymentTemplate::factory()->create();

    Livewire::test(DeploymentTemplateResource\Pages\ListDeploymentTemplates::class)
        ->callTableAction(DeleteAction::class, $template);

    $this->assertSoftDeleted($template);
});

it('can view deployment template details', function () {
    $template = DeploymentTemplate::factory()->create();

    Livewire::test(DeploymentTemplateResource\Pages\ViewDeploymentTemplate::class, [
        'record' => $template->getRouteKey(),
    ])
        ->assertFormSet([
            'script' => $template->script,
        ]);
});

it('validates required fields when creating template', function () {
    Livewire::test(DeploymentTemplateResource\Pages\CreateDeploymentTemplate::class)
        ->fillForm([
            'script' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['script' => 'required']);
});

it('shows subscription type relationship', function () {
    $subscriptionType = SubscriptionType::factory()->create(['name' => 'Laravel App']);
    $template = DeploymentTemplate::factory()->create([
        'subscription_type_id' => $subscriptionType->id,
    ]);

    Livewire::test(DeploymentTemplateResource\Pages\ListDeploymentTemplates::class)
        ->assertCanSeeTableRecords([$template])
        ->assertTableColumnExists('subscriptionType.name');
});

it('can search templates by subscription type', function () {
    $subscriptionType1 = SubscriptionType::factory()->create(['name' => 'Laravel App']);
    $subscriptionType2 = SubscriptionType::factory()->create(['name' => 'React App']);

    $template1 = DeploymentTemplate::factory()->create(['subscription_type_id' => $subscriptionType1->id]);
    $template2 = DeploymentTemplate::factory()->create(['subscription_type_id' => $subscriptionType2->id]);

    Livewire::test(DeploymentTemplateResource\Pages\ListDeploymentTemplates::class)
        ->searchTable('Laravel')
        ->assertCanSeeTableRecords([$template1])
        ->assertCanNotSeeTableRecords([$template2]);
});

it('can filter templates by subscription type', function () {
    $subscriptionType1 = SubscriptionType::factory()->create(['name' => 'Laravel App']);
    $subscriptionType2 = SubscriptionType::factory()->create(['name' => 'React App']);

    $template1 = DeploymentTemplate::factory()->create(['subscription_type_id' => $subscriptionType1->id]);
    $template2 = DeploymentTemplate::factory()->create(['subscription_type_id' => $subscriptionType2->id]);

    Livewire::test(DeploymentTemplateResource\Pages\ListDeploymentTemplates::class)
        ->filterTable('subscriptionType', $subscriptionType1->id)
        ->assertCanSeeTableRecords([$template1])
        ->assertCanNotSeeTableRecords([$template2]);
});
