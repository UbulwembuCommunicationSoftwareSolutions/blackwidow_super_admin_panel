<?php

use App\Filament\Resources\CustomerSubscriptions\CustomerSubscriptionResource;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use App\Models\User;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Storage::fake('public');
});

it('can list customer subscriptions', function () {
    $subscriptions = CustomerSubscription::factory()->count(3)->create();

    Livewire::test(CustomerSubscriptionResource\Pages\ListCustomerSubscriptions::class)
        ->assertCanSeeTableRecords($subscriptions);
});

it('can create a customer subscription', function () {
    $customer = Customer::factory()->create();
    $subscriptionType = SubscriptionType::factory()->create();

    $newData = CustomerSubscription::factory()->make([
        'customer_id' => $customer->id,
        'subscription_type_id' => $subscriptionType->id,
    ]);

    Livewire::test(CustomerSubscriptionResource\Pages\CreateCustomerSubscription::class)
        ->fillForm([
            'url' => $newData->url,
            'domain' => $newData->domain,
            'app_name' => $newData->app_name,
            'deployed_version' => $newData->deployed_version,
            'database_name' => $newData->database_name,
            'forge_site_id' => $newData->forge_site_id,
            'customer_id' => $customer->id,
            'subscription_type_id' => $subscriptionType->id,
            'panic_button_enabled' => $newData->panic_button_enabled,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('customer_subscriptions', [
        'url' => $newData->url,
        'domain' => $newData->domain,
        'app_name' => $newData->app_name,
        'customer_id' => $customer->id,
        'subscription_type_id' => $subscriptionType->id,
    ]);
});

it('can upload logo files', function () {
    $customer = Customer::factory()->create();
    $subscriptionType = SubscriptionType::factory()->create();

    $logo1 = UploadedFile::fake()->image('logo1.jpg');
    $logo2 = UploadedFile::fake()->image('logo2.png');

    Livewire::test(CustomerSubscriptionResource\Pages\CreateCustomerSubscription::class)
        ->fillForm([
            'url' => 'https://example.com',
            'domain' => 'example.com',
            'app_name' => 'Test App',
            'customer_id' => $customer->id,
            'subscription_type_id' => $subscriptionType->id,
            'logo_1' => $logo1,
            'logo_2' => $logo2,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $subscription = CustomerSubscription::latest()->first();

    expect($subscription->logo_1)->not->toBeNull();
    expect($subscription->logo_2)->not->toBeNull();

    Storage::disk('public')->assertExists($subscription->logo_1);
    Storage::disk('public')->assertExists($subscription->logo_2);
});

it('can edit a customer subscription', function () {
    $subscription = CustomerSubscription::factory()->create();
    $newData = CustomerSubscription::factory()->make();

    Livewire::test(CustomerSubscriptionResource\Pages\EditCustomerSubscription::class, [
        'record' => $subscription->getRouteKey(),
    ])
        ->fillForm([
            'url' => $newData->url,
            'domain' => $newData->domain,
            'app_name' => $newData->app_name,
            'deployed_version' => $newData->deployed_version,
            'database_name' => $newData->database_name,
            'forge_site_id' => $newData->forge_site_id,
            'panic_button_enabled' => $newData->panic_button_enabled,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($subscription->fresh())
        ->url->toBe($newData->url)
        ->domain->toBe($newData->domain)
        ->app_name->toBe($newData->app_name);
});

it('can delete a customer subscription', function () {
    $subscription = CustomerSubscription::factory()->create();

    Livewire::test(CustomerSubscriptionResource\Pages\ListCustomerSubscriptions::class)
        ->callTableAction(DeleteAction::class, $subscription);

    $this->assertSoftDeleted($subscription);
});

it('can view customer subscription details', function () {
    $subscription = CustomerSubscription::factory()->create();

    Livewire::test(CustomerSubscriptionResource\Pages\ViewCustomerSubscription::class, [
        'record' => $subscription->getRouteKey(),
    ])
        ->assertFormSet([
            'url' => $subscription->url,
            'domain' => $subscription->domain,
            'app_name' => $subscription->app_name,
        ]);
});

it('validates required fields when creating subscription', function () {
    Livewire::test(CustomerSubscriptionResource\Pages\CreateCustomerSubscription::class)
        ->fillForm([
            'url' => '',
            'domain' => '',
            'app_name' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['url' => 'required']);
});

it('can filter subscriptions by customer', function () {
    $customer1 = Customer::factory()->create(['company_name' => 'Customer A']);
    $customer2 = Customer::factory()->create(['company_name' => 'Customer B']);

    $subscription1 = CustomerSubscription::factory()->create(['customer_id' => $customer1->id]);
    $subscription2 = CustomerSubscription::factory()->create(['customer_id' => $customer2->id]);

    Livewire::test(CustomerSubscriptionResource\Pages\ListCustomerSubscriptions::class)
        ->filterTable('customer', $customer1->id)
        ->assertCanSeeTableRecords([$subscription1])
        ->assertCanNotSeeTableRecords([$subscription2]);
});

it('can search subscriptions by app name', function () {
    $subscription1 = CustomerSubscription::factory()->create(['app_name' => 'My App']);
    $subscription2 = CustomerSubscription::factory()->create(['app_name' => 'Other App']);

    Livewire::test(CustomerSubscriptionResource\Pages\ListCustomerSubscriptions::class)
        ->searchTable('My App')
        ->assertCanSeeTableRecords([$subscription1])
        ->assertCanNotSeeTableRecords([$subscription2]);
});

it('shows customer and subscription type relationships', function () {
    $customer = Customer::factory()->create(['company_name' => 'Test Customer']);
    $subscriptionType = SubscriptionType::factory()->create(['name' => 'Test Type']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $subscriptionType->id,
    ]);

    Livewire::test(CustomerSubscriptionResource\Pages\ListCustomerSubscriptions::class)
        ->assertCanSeeTableRecords([$subscription])
        ->assertTableColumnExists('customer.company_name')
        ->assertTableColumnExists('subscriptionType.name');
});

it('can toggle panic button', function () {
    $subscription = CustomerSubscription::factory()->create(['panic_button_enabled' => false]);

    Livewire::test(CustomerSubscriptionResource\Pages\EditCustomerSubscription::class, [
        'record' => $subscription->getRouteKey(),
    ])
        ->fillForm(['panic_button_enabled' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($subscription->fresh())->panic_button_enabled->toBeTrue();
});

it('validates file upload types', function () {
    $customer = Customer::factory()->create();
    $subscriptionType = SubscriptionType::factory()->create();

    $invalidFile = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

    Livewire::test(CustomerSubscriptionResource\Pages\CreateCustomerSubscription::class)
        ->fillForm([
            'url' => 'https://example.com',
            'domain' => 'example.com',
            'app_name' => 'Test App',
            'customer_id' => $customer->id,
            'subscription_type_id' => $subscriptionType->id,
            'logo_1' => $invalidFile,
        ])
        ->call('create')
        ->assertHasFormErrors(['logo_1']);
});
