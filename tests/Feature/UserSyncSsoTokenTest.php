<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake();
    Queue::fake();
});

/**
 * @return array{customer: Customer, subscription: CustomerSubscription}
 */
function ssoTokenTenant(): array
{
    $customer = Customer::factory()->create(['token' => 'console-token']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://console.example.test',
        'app_name' => 'Console',
    ]);

    return compact('customer', 'subscription');
}

function ssoTokenUser(Customer $customer, array $attributes = []): CustomerUser
{
    return CustomerUser::factory()->create(array_merge([
        'customer_id' => $customer->id,
        'email_address' => 'sso-user@tenant.test',
        'console_access' => true,
        'skip_sync' => true,
    ], $attributes));
}

function requestSsoToken(array $user, string $bearer = 'console-token'): TestResponse
{
    return test()->withToken($bearer)->postJson('/api/v1/sync/users/sso-token', [
        'app_url' => 'https://console.example.test',
        'user' => $user,
    ]);
}

it('mints a working hub token for a user with access to the calling app', function () {
    ['customer' => $customer] = ssoTokenTenant();
    $user = ssoTokenUser($customer);

    $response = requestSsoToken(['email' => $user->email_address])->assertSuccessful();

    $token = $response->json('token');
    expect($token)->toBeString()->not->toBeEmpty();

    $stored = PersonalAccessToken::findToken($token);
    expect($stored)->not->toBeNull()
        ->and($stored->tokenable_id)->toBe($user->id)
        ->and($stored->tokenable_type)->toBe(CustomerUser::class);
});

it('locates the user by hub id', function () {
    ['customer' => $customer] = ssoTokenTenant();
    $user = ssoTokenUser($customer);

    requestSsoToken(['super_admin_user_id' => $user->id])->assertSuccessful();

    expect($user->tokens()->count())->toBe(1);
});

it('refuses a user without access to the calling app', function () {
    ['customer' => $customer] = ssoTokenTenant();
    $user = ssoTokenUser($customer, ['console_access' => false]);

    requestSsoToken(['email' => $user->email_address])->assertForbidden();

    expect($user->tokens()->count())->toBe(0);
});

it('does not mint for a user of another customer', function () {
    ssoTokenTenant();
    $other = Customer::factory()->create();
    $user = ssoTokenUser($other);

    requestSsoToken(['email' => $user->email_address])->assertNotFound();

    expect($user->tokens()->count())->toBe(0);
});

it('does not mint for an archived user', function () {
    ['customer' => $customer] = ssoTokenTenant();
    $user = ssoTokenUser($customer);
    $user->delete();

    requestSsoToken(['email' => $user->email_address])->assertNotFound();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('rejects a caller with the wrong customer token', function () {
    ['customer' => $customer] = ssoTokenTenant();
    $user = ssoTokenUser($customer);

    requestSsoToken(['email' => $user->email_address], 'wrong-token')->assertUnauthorized();

    expect($user->tokens()->count())->toBe(0);
});

it('requires a way to identify the user', function () {
    ssoTokenTenant();

    requestSsoToken([])->assertUnprocessable();
});
