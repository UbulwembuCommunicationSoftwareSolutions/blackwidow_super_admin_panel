<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Http::fake();
});

function bearerAuthSetup(): array
{
    $customer = Customer::factory()->create(['token' => 'valid-customer-token']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-auth.example.test',
    ]);

    return compact('customer', 'subscription');
}

it('rejects user-import without a bearer token', function () {
    ['subscription' => $subscription] = bearerAuthSetup();

    $this->postJson('/api/user-import', [
        'app_url' => $subscription->url,
    ])->assertUnauthorized();
});

it('rejects user-import with a wrong bearer token', function () {
    ['subscription' => $subscription] = bearerAuthSetup();

    $this->withToken('wrong-token')->postJson('/api/user-import', [
        'app_url' => $subscription->url,
    ])->assertUnauthorized();
});

it('allows user-import with the matching customer token', function () {
    ['subscription' => $subscription] = bearerAuthSetup();

    CustomerUser::factory()->create([
        'customer_id' => $subscription->customer_id,
        'skip_sync' => true,
    ]);

    $this->withToken('valid-customer-token')->postJson('/api/user-import', [
        'app_url' => $subscription->url,
    ])->assertSuccessful()
        ->assertJsonPath('success', true);
});

it('allows create-user with the matching customer token', function () {
    ['subscription' => $subscription] = bearerAuthSetup();

    $this->withToken('valid-customer-token')->postJson('/api/create-user', [
        'app_url' => $subscription->url,
        'password' => 'SecretPass123!',
        'user' => [
            'first_name' => 'Auth',
            'last_name' => 'Test',
            'email' => 'auth-create@example.test',
            'console_access' => true,
        ],
    ])->assertSuccessful()
        ->assertJsonPath('success', true);
});

it('leaves user-login public without a bearer token', function () {
    ['subscription' => $subscription] = bearerAuthSetup();

    CustomerUser::factory()->create([
        'customer_id' => $subscription->customer_id,
        'email_address' => 'login-public@example.test',
        'password' => 'secret-password',
        'console_access' => true,
        'skip_sync' => true,
    ]);

    $this->postJson('/api/user-login', [
        'app_url' => $subscription->url,
        'email' => 'login-public@example.test',
        'password' => 'secret-password',
    ])->assertSuccessful();
});
