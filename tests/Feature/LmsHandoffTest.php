<?php

use App\Models\Customer;
use App\Models\CustomerUser;
use Illuminate\Support\Facades\Http;
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

it('rejects a cookie when the user has no LMS access', function () {
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
        ->assertRedirect('https://lms-hub.example.test/admin/login?error=unavailable');
});

it('rejects a missing cookie', function () {
    $this->get('/lms')
        ->assertRedirect('https://lms-hub.example.test/admin/login?error=unavailable');
});

it('rejects a consume url that is not on the configured hub host', function () {
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
        ->assertRedirect('https://lms-hub.example.test/admin/login?error=unavailable');
});
