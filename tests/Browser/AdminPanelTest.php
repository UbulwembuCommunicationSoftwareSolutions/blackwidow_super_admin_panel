<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can access the admin panel', function () {
    visit('/admin')
        ->assertSee('Admin Panel')
        ->assertNoJavascriptErrors();
});

it('can navigate to customers page', function () {
    $customer = Customer::factory()->create();

    visit('/admin/customers')
        ->assertSee('Customers')
        ->assertSee($customer->company_name)
        ->assertNoJavascriptErrors();
});

it('can create a new customer through browser', function () {
    visit('/admin/customers/create')
        ->assertSee('Create Customer')
        ->type('company_name', 'Test Company')
        ->type('token', 'test-token-123')
        ->type('docket_description', 'Test docket description')
        ->type('task_description', 'Test task description')
        ->type('max_users', '10')
        ->press('Create')
        ->assertSee('Customer created successfully')
        ->assertNoJavascriptErrors();

    $this->assertDatabaseHas('customers', [
        'company_name' => 'Test Company',
        'token' => 'test-token-123',
    ]);
});

it('can edit a customer through browser', function () {
    $customer = Customer::factory()->create(['company_name' => 'Original Name']);

    visit("/admin/customers/{$customer->id}/edit")
        ->assertSee('Edit Customer')
        ->assertInputValue('company_name', 'Original Name')
        ->type('company_name', 'Updated Company Name')
        ->press('Save')
        ->assertSee('Customer updated successfully')
        ->assertNoJavascriptErrors();

    expect($customer->fresh())->company_name->toBe('Updated Company Name');
});

it('can delete a customer through browser', function () {
    $customer = Customer::factory()->create();

    visit('/admin/customers')
        ->assertSee($customer->company_name)
        ->click('Delete')
        ->press('Delete')
        ->assertSee('Customer deleted successfully')
        ->assertNoJavascriptErrors();

    $this->assertSoftDeleted($customer);
});

it('can navigate to customer subscriptions page', function () {
    $subscription = CustomerSubscription::factory()->create();

    visit('/admin/customer-subscriptions')
        ->assertSee('Customer Subscriptions')
        ->assertSee($subscription->app_name)
        ->assertNoJavascriptErrors();
});

it('can create a customer subscription through browser', function () {
    $customer = Customer::factory()->create();
    $subscriptionType = SubscriptionType::factory()->create();

    visit('/admin/customer-subscriptions/create')
        ->assertSee('Create Customer Subscription')
        ->type('url', 'https://example.com')
        ->type('domain', 'example.com')
        ->type('app_name', 'Test App')
        ->type('deployed_version', '1.0.0')
        ->type('database_name', 'test_db')
        ->type('forge_site_id', '12345')
        ->select('customer_id', $customer->id)
        ->select('subscription_type_id', $subscriptionType->id)
        ->press('Create')
        ->assertSee('Customer subscription created successfully')
        ->assertNoJavascriptErrors();

    $this->assertDatabaseHas('customer_subscriptions', [
        'url' => 'https://example.com',
        'domain' => 'example.com',
        'app_name' => 'Test App',
        'customer_id' => $customer->id,
        'subscription_type_id' => $subscriptionType->id,
    ]);
});

it('can navigate to users page', function () {
    $user = User::factory()->create();

    visit('/admin/users')
        ->assertSee('Users')
        ->assertSee($user->name)
        ->assertNoJavascriptErrors();
});

it('can create a user through browser', function () {
    $role = Role::create(['name' => 'test-role']);

    visit('/admin/users/create')
        ->assertSee('Create User')
        ->type('name', 'Test User')
        ->type('email', 'test@example.com')
        ->type('password', 'password123')
        ->select('roles', $role->id)
        ->press('Create')
        ->assertSee('User created successfully')
        ->assertNoJavascriptErrors();

    $this->assertDatabaseHas('users', [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $user = User::where('email', 'test@example.com')->first();
    expect($user->hasRole('test-role'))->toBeTrue();
});

it('can navigate to subscription types page', function () {
    $subscriptionType = SubscriptionType::factory()->create();

    visit('/admin/subscription-types')
        ->assertSee('Subscription Types')
        ->assertSee($subscriptionType->name)
        ->assertNoJavascriptErrors();
});

it('can create a subscription type through browser', function () {
    visit('/admin/subscription-types/create')
        ->assertSee('Create Subscription Type')
        ->type('name', 'Laravel App')
        ->type('github_repo', 'laravel/laravel')
        ->type('branch', 'main')
        ->type('project_type', 'Laravel')
        ->type('master_version', '10.0.0')
        ->press('Create')
        ->assertSee('Subscription type created successfully')
        ->assertNoJavascriptErrors();

    $this->assertDatabaseHas('subscription_types', [
        'name' => 'Laravel App',
        'github_repo' => 'laravel/laravel',
        'branch' => 'main',
        'project_type' => 'Laravel',
        'master_version' => '10.0.0',
    ]);
});

it('can search customers in the browser', function () {
    $customer1 = Customer::factory()->create(['company_name' => 'Acme Corp']);
    $customer2 = Customer::factory()->create(['company_name' => 'Beta Inc']);

    visit('/admin/customers')
        ->type('tableSearchQuery', 'Acme')
        ->press('Search')
        ->assertSee('Acme Corp')
        ->assertDontSee('Beta Inc')
        ->assertNoJavascriptErrors();
});

it('can filter customers by trashed status in browser', function () {
    $activeCustomer = Customer::factory()->create(['company_name' => 'Active Customer']);
    $deletedCustomer = Customer::factory()->create(['company_name' => 'Deleted Customer']);
    $deletedCustomer->delete();

    visit('/admin/customers')
        ->click('Filters')
        ->select('trashed', 'trashed')
        ->press('Apply')
        ->assertSee('Deleted Customer')
        ->assertDontSee('Active Customer')
        ->assertNoJavascriptErrors();
});

it('can upload logo files through browser', function () {
    $customer = Customer::factory()->create();
    $subscriptionType = SubscriptionType::factory()->create();

    visit('/admin/customer-subscriptions/create')
        ->type('url', 'https://example.com')
        ->type('domain', 'example.com')
        ->type('app_name', 'Test App')
        ->select('customer_id', $customer->id)
        ->select('subscription_type_id', $subscriptionType->id)
        ->attach('logo_1', __DIR__ . '/../../fixtures/test-logo.jpg')
        ->press('Create')
        ->assertSee('Customer subscription created successfully')
        ->assertNoJavascriptErrors();
});

it('can toggle panic button in browser', function () {
    $subscription = CustomerSubscription::factory()->create(['panic_button_enabled' => false]);

    visit("/admin/customer-subscriptions/{$subscription->id}/edit")
        ->assertSee('Edit Customer Subscription')
        ->check('panic_button_enabled')
        ->press('Save')
        ->assertSee('Customer subscription updated successfully')
        ->assertNoJavascriptErrors();

    expect($subscription->fresh())->panic_button_enabled->toBeTrue();
});

it('can view customer details in browser', function () {
    $customer = Customer::factory()->create();

    visit("/admin/customers/{$customer->id}")
        ->assertSee('View Customer')
        ->assertSee($customer->company_name)
        ->assertSee($customer->token)
        ->assertNoJavascriptErrors();
});

it('can navigate between different resource pages', function () {
    visit('/admin/customers')
        ->assertSee('Customers')
        ->click('Customer Subscriptions')
        ->assertSee('Customer Subscriptions')
        ->click('Users')
        ->assertSee('Users')
        ->click('Subscription Types')
        ->assertSee('Subscription Types')
        ->assertNoJavascriptErrors();
});

it('can handle form validation errors in browser', function () {
    visit('/admin/customers/create')
        ->press('Create')
        ->assertSee('The company name field is required')
        ->assertNoJavascriptErrors();
});

it('can handle bulk actions in browser', function () {
    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();

    visit('/admin/customers')
        ->check("recordCheckbox.{$customer1->id}")
        ->check("recordCheckbox.{$customer2->id}")
        ->click('Bulk Actions')
        ->click('Delete')
        ->press('Delete')
        ->assertSee('Customers deleted successfully')
        ->assertNoJavascriptErrors();

    $this->assertSoftDeleted($customer1);
    $this->assertSoftDeleted($customer2);
});

it('can handle pagination in browser', function () {
    Customer::factory()->count(25)->create();

    visit('/admin/customers')
        ->assertSee('Next')
        ->click('Next')
        ->assertSee('Previous')
        ->assertNoJavascriptErrors();
});

it('can handle sorting in browser', function () {
    $customerA = Customer::factory()->create(['company_name' => 'A Company']);
    $customerB = Customer::factory()->create(['company_name' => 'B Company']);

    visit('/admin/customers')
        ->click('Company Name')
        ->assertSeeInOrder(['A Company', 'B Company'])
        ->click('Company Name')
        ->assertSeeInOrder(['B Company', 'A Company'])
        ->assertNoJavascriptErrors();
});
