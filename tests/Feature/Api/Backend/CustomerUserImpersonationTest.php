<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config(['user_sync.enabled' => false]);
});

it('returns the console impersonation link for a user with console access', function () {
    actingAsBackendUser();
    $customer = Customer::factory()->create(['token' => 'console-token']);
    $consoleType = SubscriptionType::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $consoleType->id,
        'url' => 'https://console.example.test',
    ]);
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
        'cms_user_id' => 501,
        'console_access' => true,
    ]);

    expect($consoleType->id)->toBe(1);

    Http::fake([
        '*/admin-api/impersonate' => Http::response([
            'message' => 'Impersonation link issued',
            'impersonate_url' => 'https://console.example.test/impersonate/consume/raw-token',
            'expires_in_minutes' => 5,
        ], 200),
    ]);

    $this->postJson("/api/backend/customer-users/{$user->id}/impersonate", [
        'customer_subscription_id' => $subscription->id,
    ])->assertOk()
        ->assertJsonPath('impersonate_url', 'https://console.example.test/impersonate/consume/raw-token')
        ->assertJsonPath('expires_in_minutes', 5);

    Http::assertSent(function ($request) use ($user) {
        return $request->url() === 'https://console.example.test/admin-api/impersonate'
            && $request->hasHeader('Authorization', 'Bearer console-token')
            && (int) $request['user_id'] === (int) $user->cms_user_id
            && (int) $request['super_admin_user_id'] === $user->id;
    });
});

it('rejects impersonation for a non-console subscription', function () {
    actingAsBackendUser();
    $customer = Customer::factory()->create();
    SubscriptionType::factory()->create();
    $firearmType = SubscriptionType::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $firearmType->id,
    ]);
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
        'cms_user_id' => 501,
        'firearm_access' => true,
    ]);

    Http::fake();

    $this->postJson("/api/backend/customer-users/{$user->id}/impersonate", [
        'customer_subscription_id' => $subscription->id,
    ])->assertStatus(422);

    Http::assertNothingSent();
});

it('rejects impersonation when the user lacks console access', function () {
    actingAsBackendUser();
    $customer = Customer::factory()->create();
    $consoleType = SubscriptionType::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $consoleType->id,
    ]);
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
        'cms_user_id' => 501,
        'console_access' => false,
    ]);

    Http::fake();

    $this->postJson("/api/backend/customer-users/{$user->id}/impersonate", [
        'customer_subscription_id' => $subscription->id,
    ])->assertStatus(422);

    Http::assertNothingSent();
});

it('reports a gateway error when the console cannot mint a link', function () {
    actingAsBackendUser();
    $customer = Customer::factory()->create(['token' => 'console-token']);
    $consoleType = SubscriptionType::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $consoleType->id,
        'url' => 'https://console.example.test',
    ]);
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
        'cms_user_id' => 501,
        'console_access' => true,
    ]);

    Http::fake([
        '*/admin-api/impersonate' => Http::response(['message' => 'Server error'], 500),
    ]);

    $this->postJson("/api/backend/customer-users/{$user->id}/impersonate", [
        'customer_subscription_id' => $subscription->id,
    ])->assertStatus(502);
});

it('requires a token to impersonate', function () {
    $customer = Customer::factory()->create();
    $consoleType = SubscriptionType::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $consoleType->id,
    ]);
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
        'cms_user_id' => 501,
        'console_access' => true,
    ]);

    $this->postJson("/api/backend/customer-users/{$user->id}/impersonate", [
        'customer_subscription_id' => $subscription->id,
    ])->assertUnauthorized();
});
