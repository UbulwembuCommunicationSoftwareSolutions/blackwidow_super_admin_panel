<?php

use App\Models\Customer;
use App\Models\CustomerUser;
use App\Support\Sso\SsoHandoffLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config([
        'services.lms.hub_url' => 'https://lms-hub.example.test',
        'services.lms.sync_token' => 'lms-hub-token',
        'customer_sync.enabled' => false,
        'user_sync.enabled' => false,
    ]);
});

it('redirects an lms-access cookie to a one-time LMS consume link', function () {
    Http::fake([
        'https://lms-hub.example.test/admin-api/impersonate' => Http::response([
            'message' => 'Impersonation link issued',
            'user_id' => 42,
            'super_admin_user_id' => 1,
            'impersonate_url' => 'https://lms-hub.example.test/impersonate/consume/raw-token',
            'expires_in_minutes' => 5,
        ], 200),
        'https://lms-hub.example.test/*' => Http::response(['success' => true], 200),
    ]);

    $customer = Customer::factory()->create();
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'lms_access' => true,
        'is_system_admin' => false,
        'lms_user_id' => null,
        'skip_sync' => true,
        'password' => 'secret-pass',
    ]);
    $plain = $user->createToken('customer-user-token')->plainTextToken;

    $this->withUnencryptedCookie('external_token', $plain)
        ->get('/lms')
        ->assertRedirect('https://lms-hub.example.test/impersonate/consume/raw-token');

    expect($user->fresh()->lms_user_id)->toBe(42);

    Http::assertSent(function ($request) use ($user) {
        return $request->url() === 'https://lms-hub.example.test/admin-api/impersonate'
            && $request->hasHeader('Authorization', 'Bearer lms-hub-token')
            && (int) $request['super_admin_user_id'] === (int) $user->id
            && ! array_key_exists('user_id', $request->data());
    });
});

it('sends known lms_user_id when minting a consume link', function () {
    Http::fake([
        'https://lms-hub.example.test/admin-api/impersonate' => Http::response([
            'message' => 'Impersonation link issued',
            'user_id' => 99,
            'impersonate_url' => 'https://lms-hub.example.test/impersonate/consume/known',
            'expires_in_minutes' => 5,
        ], 200),
        'https://lms-hub.example.test/*' => Http::response(['success' => true], 200),
    ]);

    $customer = Customer::factory()->create();
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'lms_access' => true,
        'lms_user_id' => 99,
        'skip_sync' => true,
        'password' => 'secret-pass',
    ]);
    $plain = $user->createToken('customer-user-token')->plainTextToken;

    $this->withUnencryptedCookie('external_token', $plain)
        ->get('/lms')
        ->assertRedirect('https://lms-hub.example.test/impersonate/consume/known');

    Http::assertSent(function ($request) use ($user) {
        return $request->url() === 'https://lms-hub.example.test/admin-api/impersonate'
            && (int) $request['super_admin_user_id'] === (int) $user->id
            && (int) $request['user_id'] === 99;
    });
});

it('allows a system admin without lms_access to open the LMS handoff', function () {
    Http::fake([
        'https://lms-hub.example.test/admin-api/impersonate' => Http::response([
            'user_id' => 7,
            'impersonate_url' => 'https://lms-hub.example.test/impersonate/consume/admin',
            'expires_in_minutes' => 5,
        ], 200),
        'https://lms-hub.example.test/*' => Http::response(['success' => true], 200),
    ]);

    $customer = Customer::factory()->create();
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'lms_access' => false,
        'is_system_admin' => true,
        'skip_sync' => true,
        'password' => 'secret-pass',
    ]);
    $plain = $user->createToken('customer-user-token')->plainTextToken;

    $this->withUnencryptedCookie('external_token', $plain)
        ->get('/lms')
        ->assertRedirect('https://lms-hub.example.test/impersonate/consume/admin');
});

it('sends a user without LMS access to the LMS itself and logs why', function () {
    Log::spy();
    Http::fake([
        'https://lms-hub.example.test/*' => Http::response(['success' => true], 200),
    ]);

    $customer = Customer::factory()->create();
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'lms_access' => false,
        'is_system_admin' => false,
        'skip_sync' => true,
        'password' => 'secret-pass',
    ]);
    $plain = $user->createToken('customer-user-token')->plainTextToken;

    $this->withUnencryptedCookie('external_token', $plain)
        ->get('/lms')
        ->assertRedirect('https://lms-hub.example.test');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === SsoHandoffLog::REFUSED
        && $context['handoff'] === 'lms'
        && $context['reason'] === 'no_access'
        && $context['customer_user_id'] === $user->id);
});

it('sends a missing cookie to the LMS itself and logs why', function () {
    Log::spy();

    $this->get('/lms')->assertRedirect('https://lms-hub.example.test');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === SsoHandoffLog::REFUSED
        && $context['reason'] === 'no_cookie'
        && $context['has_cookie'] === false);
});

it('refuses a consume url that is not on the configured hub host', function () {
    Log::spy();
    Http::fake([
        'https://lms-hub.example.test/admin-api/impersonate' => Http::response([
            'user_id' => 1,
            'impersonate_url' => 'https://evil.example.test/impersonate/consume/raw',
            'expires_in_minutes' => 5,
        ], 200),
        'https://lms-hub.example.test/*' => Http::response(['success' => true], 200),
    ]);

    $customer = Customer::factory()->create();
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'lms_access' => true,
        'skip_sync' => true,
        'password' => 'secret-pass',
    ]);
    $plain = $user->createToken('customer-user-token')->plainTextToken;

    $this->withUnencryptedCookie('external_token', $plain)
        ->get('/lms')
        ->assertRedirect('https://lms-hub.example.test');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === SsoHandoffLog::REFUSED
        && $context['reason'] === 'consume_url_off_host'
        && $context['consume_host'] === 'evil.example.test');
});

it('logs a successful lms handoff without the consume token', function () {
    Log::spy();
    Http::fake([
        'https://lms-hub.example.test/admin-api/impersonate' => Http::response([
            'user_id' => 5,
            'impersonate_url' => 'https://lms-hub.example.test/impersonate/consume/secret-token',
            'expires_in_minutes' => 5,
        ], 200),
        'https://lms-hub.example.test/*' => Http::response(['success' => true], 200),
    ]);

    $customer = Customer::factory()->create();
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'lms_access' => true,
        'skip_sync' => true,
        'password' => 'secret-pass',
    ]);
    $plain = $user->createToken('customer-user-token')->plainTextToken;

    $this->withUnencryptedCookie('external_token', $plain)->get('/lms');

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === SsoHandoffLog::REDIRECTED
        && $context['handoff'] === 'lms'
        && $context['redirect_host'] === 'lms-hub.example.test'
        && ! str_contains(json_encode($context), 'secret-token')
        && ! str_contains(json_encode($context), $plain));
});

it('logs when the lms hub is not configured', function () {
    Log::spy();
    config(['services.lms.hub_url' => null]);

    $this->get('/lms')->assertRedirect('/login?error=unavailable');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === SsoHandoffLog::REFUSED
        && $context['reason'] === 'hub_not_configured');
});
