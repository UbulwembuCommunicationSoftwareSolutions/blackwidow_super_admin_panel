<?php

use App\Jobs\PushCustomerUserPasswordJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// A tenant only learns a new password from this push; without it the previous
// password stays valid on the tenant until the user signs in with the new one.

beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
    config(['tenant_test.status' => 200]);
    Http::fake(fn () => Http::response(['success' => true, 'user' => ['cms_user_id' => 7]], config('tenant_test.status')));
});

function userOnTenant(bool $skipSync = false): CustomerUser
{
    $customer = Customer::factory()->create(['token' => 'push-token']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-push.example.test',
    ]);

    return CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => $skipSync,
    ]);
}

it('queues a password push when an admin changes a customer user password', function () {
    $user = userOnTenant();
    Queue::fake();

    $user->update(['password' => 'New-secret-123']);

    Queue::assertPushed(
        PushCustomerUserPasswordJob::class,
        fn (PushCustomerUserPasswordJob $job) => $job->customerUserId === $user->id
            && $job->password === 'New-secret-123'
    );
});

it('does not queue a password push for a routine profile edit', function () {
    $user = userOnTenant();
    Queue::fake();

    $user->update(['first_name' => 'Renamed']);

    Queue::assertNotPushed(PushCustomerUserPasswordJob::class);
});

it('does not queue a password push when the change arrived from a tenant', function () {
    $user = userOnTenant(skipSync: true);
    Queue::fake();

    $user->update(['password' => 'Tenant-origin-1']);

    Queue::assertNotPushed(PushCustomerUserPasswordJob::class);
});

it('does not reuse an old cleartext password for a later save', function () {
    $user = userOnTenant();
    $user->update(['password' => 'First-secret-1']);
    Queue::fake();

    $user->update(['first_name' => 'Renamed']);

    Queue::assertNotPushed(PushCustomerUserPasswordJob::class);
});

it('posts the cleartext password to the tenant users/password endpoint', function () {
    $user = userOnTenant();
    $user->update(['password' => 'New-secret-123']);

    (new PushCustomerUserPasswordJob($user->id, 'New-secret-123'))
        ->handle(app(App\Services\UserSync\TenantUserPusher::class));

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/admin-api/v1/sync/users/password')
        && $request['password'] === 'New-secret-123');
});

it('skips a push that a later password change has superseded', function () {
    $user = userOnTenant();
    $user->update(['password' => 'Older-secret-1']);
    $user->update(['password' => 'Newer-secret-2']);

    (new PushCustomerUserPasswordJob($user->id, 'Older-secret-1'))
        ->handle(app(App\Services\UserSync\TenantUserPusher::class));

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/users/password'));
});

it('retries when the tenant is down', function () {
    $user = userOnTenant();
    $user->update(['password' => 'New-secret-123']);
    config(['tenant_test.status' => 503]);

    expect(fn () => (new PushCustomerUserPasswordJob($user->id, 'New-secret-123'))
        ->handle(app(App\Services\UserSync\TenantUserPusher::class)))
        ->toThrow(RuntimeException::class);
});
