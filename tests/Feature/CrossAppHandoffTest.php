<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use App\Support\Sso\SsoHandoffLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Log::spy();
    config([
        'services.lms.hub_url' => 'https://lms-hub.example.test',
        'services.lms.sync_token' => 'lms-hub-token',
        'customer_sync.enabled' => false,
        'user_sync.enabled' => false,
    ]);

    SubscriptionType::factory()->count(3)->create();
});

/**
 * @param  array<string, mixed>  $userAttributes
 * @return array{customer: Customer, user: CustomerUser, token: string}
 */
function handoffUser(array $userAttributes = [], array $customerAttributes = []): array
{
    $customer = Customer::factory()->create(array_merge(['token' => 'tenant-token'], $customerAttributes));
    $user = CustomerUser::factory()->create(array_merge([
        'customer_id' => $customer->id,
        'skip_sync' => true,
        'password' => 'secret-pass',
    ], $userAttributes));

    return [
        'customer' => $customer,
        'user' => $user,
        'token' => $user->createToken('customer-user-token')->plainTextToken,
    ];
}

function handoffSubscription(Customer $customer, int $typeId, string $url): CustomerSubscription
{
    return CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $typeId,
        'url' => $url,
    ]);
}

function assertHandoffRefused(string $reason): void
{
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === SsoHandoffLog::REFUSED
        && $context['handoff'] === 'go'
        && $context['reason'] === $reason);
}

it('signs a console user into the cms with a one-time link', function () {
    ['customer' => $customer, 'user' => $user, 'token' => $token] = handoffUser(['console_access' => true, 'cms_user_id' => 501]);
    handoffSubscription($customer, 1, 'https://console.example.test');

    Http::fake([
        'https://console.example.test/admin-api/impersonate' => Http::response([
            'impersonate_url' => 'https://console.example.test/impersonate/consume/raw',
            'expires_in_minutes' => 5,
        ], 200),
    ]);

    $this->withUnencryptedCookie('external_token', $token)
        ->get('/go?to='.urlencode('https://console.example.test/dashboard'))
        ->assertRedirect('https://console.example.test/impersonate/consume/raw');

    Http::assertSent(fn ($request) => $request->url() === 'https://console.example.test/admin-api/impersonate'
        && (int) $request['super_admin_user_id'] === $user->id
        && (int) $request['user_id'] === 501);

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === SsoHandoffLog::REDIRECTED
        && $context['handoff'] === 'go'
        && $context['redirect_host'] === 'console.example.test'
        && ! str_contains(json_encode($context), 'consume/raw'));
});

it('signs a firearm user in with a one-time link', function () {
    ['customer' => $customer, 'token' => $token] = handoffUser(['firearm_access' => true]);
    handoffSubscription($customer, 2, 'https://firearm.example.test/');

    Http::fake([
        'https://firearm.example.test/admin-api/impersonate' => Http::response([
            'impersonate_url' => 'https://firearm.example.test/impersonate/consume/fa',
            'expires_in_minutes' => 5,
        ], 200),
    ]);

    $this->withUnencryptedCookie('external_token', $token)
        ->get('/go?to='.urlencode('https://firearm.example.test'))
        ->assertRedirect('https://firearm.example.test/impersonate/consume/fa');
});

it('signs an lms user into the lms hub', function () {
    ['token' => $token] = handoffUser(['lms_access' => true]);

    Http::fake([
        'https://lms-hub.example.test/admin-api/impersonate' => Http::response([
            'user_id' => 9,
            'impersonate_url' => 'https://lms-hub.example.test/impersonate/consume/lms',
            'expires_in_minutes' => 5,
        ], 200),
        'https://lms-hub.example.test/*' => Http::response(['success' => true], 200),
    ]);

    $this->withUnencryptedCookie('external_token', $token)
        ->get('/go?to='.urlencode('https://lms-hub.example.test'))
        ->assertRedirect('https://lms-hub.example.test/impersonate/consume/lms');
});

it('pushes the user to the lms before asking for a link', function () {
    config(['user_sync.enabled' => true]);
    ['user' => $user, 'token' => $token] = handoffUser(['lms_access' => true]);

    Http::fake([
        'https://lms-hub.example.test/admin-api/v1/sync/users' => Http::response([
            'success' => true,
            'user' => ['cms_user_id' => 31],
        ], 201),
        'https://lms-hub.example.test/admin-api/impersonate' => Http::response([
            'user_id' => 31,
            'impersonate_url' => 'https://lms-hub.example.test/impersonate/consume/new-member',
            'expires_in_minutes' => 5,
        ], 200),
    ]);

    $this->withUnencryptedCookie('external_token', $token)
        ->get('/go?to='.urlencode('https://lms-hub.example.test'))
        ->assertRedirect('https://lms-hub.example.test/impersonate/consume/new-member');

    Http::assertSentInOrder([
        fn ($request) => $request->url() === 'https://lms-hub.example.test/admin-api/v1/sync/users'
            && (int) $request['user']['super_admin_user_id'] === $user->id,
        fn ($request) => $request->url() === 'https://lms-hub.example.test/admin-api/impersonate'
            && (int) $request['super_admin_user_id'] === $user->id,
    ]);
});

it('still asks the lms for a link when the push fails', function () {
    config(['user_sync.enabled' => true]);
    ['token' => $token] = handoffUser(['lms_access' => true]);

    Http::fake([
        'https://lms-hub.example.test/admin-api/v1/sync/users' => Http::response(['message' => 'down'], 500),
        'https://lms-hub.example.test/admin-api/impersonate' => Http::response([
            'user_id' => 12,
            'impersonate_url' => 'https://lms-hub.example.test/impersonate/consume/existing',
            'expires_in_minutes' => 5,
        ], 200),
    ]);

    $this->withUnencryptedCookie('external_token', $token)
        ->get('/go?to='.urlencode('https://lms-hub.example.test'))
        ->assertRedirect('https://lms-hub.example.test/impersonate/consume/existing');
});

it('returns 404 for a host that is not a suite app', function () {
    ['token' => $token] = handoffUser(['console_access' => true]);

    $this->withUnencryptedCookie('external_token', $token)
        ->get('/go?to='.urlencode('https://evil.example.test/phish'))
        ->assertNotFound();

    assertHandoffRefused('unknown_target');
});

it('requires a target', function () {
    $this->get('/go')->assertRedirect();
});

it('sends a visitor without a cookie to the app login', function () {
    handoffSubscription(Customer::factory()->create(), 2, 'firearm.example.test');

    $this->get('/go?to='.urlencode('https://firearm.example.test/some/page'))
        ->assertRedirect('https://firearm.example.test');

    assertHandoffRefused('no_cookie');
});

it('sends a revoked token to the app login', function () {
    handoffSubscription(Customer::factory()->create(), 2, 'https://firearm.example.test');

    $this->withUnencryptedCookie('external_token', '1|not-a-real-token')
        ->get('/go?to='.urlencode('https://firearm.example.test'))
        ->assertRedirect('https://firearm.example.test');

    assertHandoffRefused('token_not_found');
});

it('sends a delete-scheduled user to the app login', function () {
    ['customer' => $customer, 'token' => $token] = handoffUser(['firearm_access' => true, 'delete_scheduled' => now()]);
    handoffSubscription($customer, 2, 'https://firearm.example.test');

    $this->withUnencryptedCookie('external_token', $token)
        ->get('/go?to='.urlencode('https://firearm.example.test'))
        ->assertRedirect('https://firearm.example.test');

    assertHandoffRefused('delete_scheduled');
});

it('does not treat another customer\'s app as the user\'s own', function () {
    ['token' => $token] = handoffUser(['firearm_access' => true]);
    handoffSubscription(Customer::factory()->create(), 2, 'https://other-firearm.example.test');

    Http::fake();

    $this->withUnencryptedCookie('external_token', $token)
        ->get('/go?to='.urlencode('https://other-firearm.example.test'))
        ->assertRedirect('https://other-firearm.example.test');

    Http::assertNothingSent();
    assertHandoffRefused('no_subscription');
});

it('sends a user without access to the app login', function () {
    ['customer' => $customer, 'token' => $token] = handoffUser(['firearm_access' => false]);
    handoffSubscription($customer, 2, 'https://firearm.example.test');

    Http::fake();

    $this->withUnencryptedCookie('external_token', $token)
        ->get('/go?to='.urlencode('https://firearm.example.test'))
        ->assertRedirect('https://firearm.example.test');

    Http::assertNothingSent();
    assertHandoffRefused('no_access');
});

it('sends a user without lms access to the lms login', function () {
    ['token' => $token] = handoffUser(['lms_access' => false, 'is_system_admin' => false]);

    $this->withUnencryptedCookie('external_token', $token)
        ->get('/go?to='.urlencode('https://lms-hub.example.test'))
        ->assertRedirect('https://lms-hub.example.test');

    assertHandoffRefused('no_access');
});

it('plainly redirects apps without one-time link support', function () {
    ['customer' => $customer, 'token' => $token] = handoffUser(['responder_access' => true]);
    handoffSubscription($customer, 3, 'https://responder.example.test');

    $this->withUnencryptedCookie('external_token', $token)
        ->get('/go?to='.urlencode('https://responder.example.test'))
        ->assertRedirect('https://responder.example.test');

    assertHandoffRefused('unsupported_type');
});

it('falls back to the app login when the app refuses the link', function () {
    ['customer' => $customer, 'token' => $token] = handoffUser(['firearm_access' => true]);
    handoffSubscription($customer, 2, 'https://firearm.example.test');

    Http::fake([
        'https://firearm.example.test/admin-api/impersonate' => Http::response(['message' => 'User not found'], 404),
    ]);

    $this->withUnencryptedCookie('external_token', $token)
        ->get('/go?to='.urlencode('https://firearm.example.test'))
        ->assertRedirect('https://firearm.example.test');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === SsoHandoffLog::REFUSED
        && $context['reason'] === 'link_request_failed'
        && str_contains($context['error'], '404'));
});

it('refuses a consume url on a different host', function () {
    ['customer' => $customer, 'token' => $token] = handoffUser(['firearm_access' => true]);
    handoffSubscription($customer, 2, 'https://firearm.example.test');

    Http::fake([
        'https://firearm.example.test/admin-api/impersonate' => Http::response([
            'impersonate_url' => 'https://evil.example.test/impersonate/consume/x',
        ], 200),
    ]);

    $this->withUnencryptedCookie('external_token', $token)
        ->get('/go?to='.urlencode('https://firearm.example.test'))
        ->assertRedirect('https://firearm.example.test');

    assertHandoffRefused('consume_url_off_host');
});
