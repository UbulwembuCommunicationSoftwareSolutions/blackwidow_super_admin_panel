<?php

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Models\User;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Factories\Sequence;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can list customers', function () {
    $customers = Customer::factory()->count(3)->create();

    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->assertCanSeeTableRecords($customers);
});

it('can create a customer', function () {
    $newData = Customer::factory()->make();

    Livewire::test(CustomerResource\Pages\CreateCustomer::class)
        ->fillForm([
            'company_name' => $newData->company_name,
            'token' => $newData->token,
            'docket_description' => $newData->docket_description,
            'task_description' => $newData->task_description,
            'max_users' => $newData->max_users,
            'level_one_in_use' => $newData->level_one_in_use,
            'level_two_in_use' => $newData->level_two_in_use,
            'level_three_in_use' => $newData->level_three_in_use,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('customers', [
        'company_name' => $newData->company_name,
        'token' => $newData->token,
    ]);
});

it('can edit a customer', function () {
    $customer = Customer::factory()->create();
    $newData = Customer::factory()->make();

    Livewire::test(CustomerResource\Pages\EditCustomer::class, [
        'record' => $customer->getRouteKey(),
    ])
        ->fillForm([
            'company_name' => $newData->company_name,
            'token' => $newData->token,
            'docket_description' => $newData->docket_description,
            'task_description' => $newData->task_description,
            'max_users' => $newData->max_users,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($customer->fresh())
        ->company_name->toBe($newData->company_name)
        ->token->toBe($newData->token);
});

it('can delete a customer', function () {
    $customer = Customer::factory()->create();

    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->callTableAction(DeleteAction::class, $customer);

    $this->assertSoftDeleted($customer);
});

it('can restore a customer', function () {
    $customer = Customer::factory()->create();
    $customer->delete();

    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->callTableAction(RestoreAction::class, $customer);

    expect($customer->fresh())->deleted_at->toBeNull();
});

it('can force delete a customer', function () {
    $customer = Customer::factory()->create();
    $customer->delete();

    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->callTableAction(ForceDeleteAction::class, $customer);

    $this->assertDatabaseMissing('customers', [
        'id' => $customer->id,
    ]);
});

it('can filter customers by trashed status', function () {
    $activeCustomer = Customer::factory()->create();
    $deletedCustomer = Customer::factory()->create();
    $deletedCustomer->delete();

    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->filterTable(TrashedFilter::class, 'trashed')
        ->assertCanSeeTableRecords([$deletedCustomer])
        ->assertCanNotSeeTableRecords([$activeCustomer]);
});

it('can search customers by company name', function () {
    $customer1 = Customer::factory()->create(['company_name' => 'Acme Corp']);
    $customer2 = Customer::factory()->create(['company_name' => 'Beta Inc']);

    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->searchTable('Acme')
        ->assertCanSeeTableRecords([$customer1])
        ->assertCanNotSeeTableRecords([$customer2]);
});

it('validates required fields when creating customer', function () {
    Livewire::test(CustomerResource\Pages\CreateCustomer::class)
        ->fillForm([
            'company_name' => '',
            'token' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['company_name' => 'required']);
});

it('shows customer subscriptions count in table', function () {
    $customer = Customer::factory()->create();
    $customer->customerSubscriptions()->create([
        'url' => 'https://example.com',
        'domain' => 'example.com',
        'app_name' => 'Test App',
        'subscription_type_id' => 1,
    ]);

    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->assertCanSeeTableRecords([$customer])
        ->assertTableColumnExists('customer_subscriptions_count');
});

it('can view customer details', function () {
    $customer = Customer::factory()->create();

    Livewire::test(CustomerResource\Pages\ViewCustomer::class, [
        'record' => $customer->getRouteKey(),
    ])
        ->assertFormSet([
            'company_name' => $customer->company_name,
            'token' => $customer->token,
        ]);
});

it('respects role-based access control', function () {
    $customerManager = User::factory()->create();
    $customerManager->assignRole('customer_manager');

    $otherUser = User::factory()->create();
    $otherUser->assignRole('user');

    // Test that customer manager can see customers
    $this->actingAs($customerManager);

    $customer = Customer::factory()->create();

    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->assertCanSeeTableRecords([$customer]);

    // Test that other user cannot see customers
    $this->actingAs($otherUser);

    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->assertCanNotSeeTableRecords([$customer]);
});
