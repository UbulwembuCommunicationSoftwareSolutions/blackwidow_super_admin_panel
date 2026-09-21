<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Http::fake();
});

function ssoCustomerUser(int $subscriptionTypeId, string $url, array $userAttributes = []): array
{
    $customer = Customer::factory()->create();
    SubscriptionType::factory()->create([
        'id' => $subscriptionTypeId,
        'name' => 'Type '.$subscriptionTypeId,
    ]);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $subscriptionTypeId,
        'url' => $url,
    ]);
    $user = CustomerUser::factory()->create(array_merge([
        'customer_id' => $customer->id,
        'email_address' => 'sso-'.$subscriptionTypeId.'@example.test',
        'password' => 'secret-password',
        'console_access' => $subscriptionTypeId === 1,
        'firearm_access' => $subscriptionTypeId === 2,
        'skip_sync' => true,
    ], $userAttributes));

    return compact('customer', 'subscription', 'user');
}

it('returns the customer user for a token that can access the product', function () {
    ['subscription' => $subscription, 'user' => $user] = ssoCustomerUser(1, 'https://cms.example.test');
    $token = $user->createToken('customer-user-token')->plainTextToken;

    $this->withToken($token)->postJson('/api/token-user', [
        'app_url' => $subscription->url,
    ])->assertSuccessful()
        ->assertJsonPath('user.email_address', $user->email_address)
        ->assertJsonPath('token', $token)
        ->assertJsonMissingPath('user.password');
});

it('rejects a valid token for a product the user cannot access', function () {
    ['user' => $user] = ssoCustomerUser(1, 'https://cms.example.test', [
        'firearm_access' => false,
    ]);
    SubscriptionType::factory()->create(['id' => 2, 'name' => 'Firearm']);
    $firearm = CustomerSubscription::factory()->create([
        'customer_id' => $user->customer_id,
        'subscription_type_id' => 2,
        'url' => 'https://firearm.example.test',
    ]);
    $token = $user->createToken('customer-user-token')->plainTextToken;

    $this->withToken($token)->postJson('/api/token-user', [
        'app_url' => $firearm->url,
    ])->assertUnauthorized()
        ->assertJsonPath('message', 'Access Denied');
});

it('rejects an invalid token', function () {
    ssoCustomerUser(1, 'https://cms.example.test');

    $this->withToken('not-a-real-token')->postJson('/api/token-user', [
        'app_url' => 'https://cms.example.test',
    ])->assertUnauthorized();
});

it('requires app_url', function () {
    ['user' => $user] = ssoCustomerUser(1, 'https://cms.example.test');
    $token = $user->createToken('customer-user-token')->plainTextToken;

    $this->withToken($token)->postJson('/api/token-user', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['app_url']);
});

it('deletes the current sanctum token on sso logout', function () {
    ['user' => $user] = ssoCustomerUser(2, 'https://firearm.example.test');
    $token = $user->createToken('customer-user-token')->plainTextToken;
    $tokenId = (int) explode('|', $token)[0];

    $this->withToken($token)->postJson('/api/sso-logout')
        ->assertSuccessful();

    expect(PersonalAccessToken::query()->find($tokenId))->toBeNull();
});
