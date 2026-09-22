<?php

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\RelationManagers\CustomerSubscriptionsRelationManager;
use App\Models\Customer;
use App\Models\SubscriptionType;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $user = User::factory()->create();
    grantBackendPermissions($user);
    $this->actingAs($user);
});

it('derives postfix and database name for the aims.net.za vertical', function () {
    $customer = Customer::factory()->create(['company_name' => 'Acme Security']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'console']);

    $component = Livewire::test(CustomerSubscriptionsRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ])
        ->mountTableAction('create');

    $statePath = $component->instance()->{$component->instance()->getMountedActionSchemaName()}->getStatePath();

    $component
        ->set("{$statePath}.customer_id", $customer->id)
        ->set("{$statePath}.subscription_type_id", 1)
        ->set("{$statePath}.vertical", 'aims.net.za')
        ->set("{$statePath}.url", 'customer');

    expect(data_get($component->instance(), "{$statePath}.postfix"))
        ->toBe('.console.aims.net.za')
        ->and(data_get($component->instance(), "{$statePath}.database_name"))
        ->toBe('customer_console_aims_net_za')
        ->and(data_get($component->instance(), "{$statePath}.theVertical"))
        ->toBe('aims_net_za');
});

it('uses firearm not firearm-module in the host for a Firearm Module subscription', function () {
    $customer = Customer::factory()->create(['company_name' => 'Demo']);
    SubscriptionType::factory()->create(['id' => 2, 'name' => 'Firearm Module']);

    $component = Livewire::test(CustomerSubscriptionsRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ])
        ->mountTableAction('create');

    $statePath = $component->instance()->{$component->instance()->getMountedActionSchemaName()}->getStatePath();

    $component
        ->set("{$statePath}.customer_id", $customer->id)
        ->set("{$statePath}.subscription_type_id", 2)
        ->set("{$statePath}.vertical", 'blackwidow.org.za')
        ->set("{$statePath}.url", 'demo');

    expect(data_get($component->instance(), "{$statePath}.postfix"))
        ->toBe('.firearm.blackwidow.org.za')
        ->and(data_get($component->instance(), "{$statePath}.theType"))
        ->toBe('firearm')
        ->and(data_get($component->instance(), "{$statePath}.database_name"))
        ->toBe('demo_firearm_blackwidow');
});
