<?php

use App\Models\Customer;
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

it('returns an lms admin link for super admin staff', function () {
    Http::fake([
        'https://lms-hub.example.test/admin-api/staff-impersonate' => Http::response([
            'message' => 'Impersonation link issued',
            'user_id' => 3,
            'impersonate_url' => 'https://lms-hub.example.test/impersonate/consume/staff-token',
            'expires_in_minutes' => 5,
        ], 200),
    ]);

    $staff = actingAsBackendUser();

    $this->postJson('/api/backend/lms/access')
        ->assertOk()
        ->assertJsonPath('impersonate_url', 'https://lms-hub.example.test/impersonate/consume/staff-token')
        ->assertJsonPath('expires_in_minutes', 5);

    Http::assertSent(function ($request) use ($staff) {
        return $request->url() === 'https://lms-hub.example.test/admin-api/staff-impersonate'
            && $request->hasHeader('Authorization', 'Bearer lms-hub-token')
            && (int) $request['super_admin_staff_id'] === $staff->id
            && $request['email'] === $staff->email
            && $request['name'] === $staff->name;
    });
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/admin-api/impersonate'));
});

it('pushes a customer admin to the lms and returns a member link', function () {
    config(['user_sync.enabled' => true]);

    Http::fake([
        'https://lms-hub.example.test/admin-api/v1/sync/users' => Http::response([
            'success' => true,
            'user' => ['cms_user_id' => 77],
        ], 201),
        'https://lms-hub.example.test/admin-api/impersonate' => Http::response([
            'message' => 'Impersonation link issued',
            'user_id' => 77,
            'impersonate_url' => 'https://lms-hub.example.test/impersonate/consume/member-token',
            'expires_in_minutes' => 5,
        ], 200),
    ]);

    $customer = Customer::factory()->create();
    $admin = actingAsCustomerAdmin($customer, ['lms_access' => false]);

    $this->postJson('/api/backend/lms/access')
        ->assertOk()
        ->assertJsonPath('impersonate_url', 'https://lms-hub.example.test/impersonate/consume/member-token');

    expect($admin->fresh()->lms_user_id)->toBe(77);

    Http::assertSentInOrder([
        fn ($request) => $request->url() === 'https://lms-hub.example.test/admin-api/v1/sync/users'
            && (int) $request['user']['super_admin_user_id'] === $admin->id
            && $request['user']['is_system_admin'] === true,
        fn ($request) => $request->url() === 'https://lms-hub.example.test/admin-api/impersonate'
            && (int) $request['super_admin_user_id'] === $admin->id,
    ]);
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/admin-api/staff-impersonate'));
});

it('still issues a member link when the push to the lms fails', function () {
    config(['user_sync.enabled' => true]);

    Http::fake([
        'https://lms-hub.example.test/admin-api/v1/sync/users' => Http::response(['message' => 'down'], 500),
        'https://lms-hub.example.test/admin-api/impersonate' => Http::response([
            'user_id' => 12,
            'impersonate_url' => 'https://lms-hub.example.test/impersonate/consume/existing',
            'expires_in_minutes' => 5,
        ], 200),
    ]);

    actingAsCustomerAdmin();

    $this->postJson('/api/backend/lms/access')
        ->assertOk()
        ->assertJsonPath('impersonate_url', 'https://lms-hub.example.test/impersonate/consume/existing');
});

it('explains when the customer admin is not on the lms yet', function () {
    Http::fake([
        'https://lms-hub.example.test/admin-api/impersonate' => Http::response(['message' => 'User not found'], 404),
    ]);

    actingAsCustomerAdmin();

    $this->postJson('/api/backend/lms/access')
        ->assertStatus(502)
        ->assertJsonPath('message', 'Your account is not set up on the LMS yet. Please try again shortly or contact support.');
});

it('reports when the lms refuses the staff link', function () {
    Http::fake([
        'https://lms-hub.example.test/admin-api/staff-impersonate' => Http::response(['message' => 'Conflict'], 409),
    ]);

    actingAsBackendUser();

    $this->postJson('/api/backend/lms/access')->assertStatus(502);
});

it('returns 503 when the lms hub is not configured', function (string $key) {
    config([$key => null]);
    Http::fake();

    actingAsBackendUser();

    $this->postJson('/api/backend/lms/access')
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'The LMS is not configured on this Super Admin.');

    Http::assertNothingSent();
})->with(['services.lms.hub_url', 'services.lms.sync_token']);

it('refuses an archived customer admin', function () {
    Http::fake();

    actingAsCustomerAdmin(null, ['delete_scheduled' => now()]);

    $this->postJson('/api/backend/lms/access')->assertForbidden();

    Http::assertNothingSent();
});

it('requires authentication', function () {
    $this->postJson('/api/backend/lms/access')->assertUnauthorized();
});
