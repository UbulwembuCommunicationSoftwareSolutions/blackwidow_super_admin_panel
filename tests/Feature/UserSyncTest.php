<?php

use App\Jobs\PushCustomerUserToTenantsJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use App\Services\UserSync\TenantUserPusher;
use App\Support\UserSync\PushOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['success' => true], 200)]);
});

function tenantCustomer(string $token = 'push-token', string $url = 'https://cms-push.example.test'): array
{
    $customer = Customer::factory()->create(['token' => $token]);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => $url,
    ]);

    return compact('customer', 'subscription');
}

it('queues a per-record push when an admin creates a customer user', function () {
    ['customer' => $customer] = tenantCustomer();

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => false,
    ]);

    Queue::assertPushed(
        PushCustomerUserToTenantsJob::class,
        fn (PushCustomerUserToTenantsJob $job) => $job->customerUserId === $user->id
            && $job->operation === PushOperation::Upsert
    );
});

it('queues a per-record push when an admin updates a customer user', function () {
    ['customer' => $customer] = tenantCustomer();

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
    ]);

    Queue::fake();

    $user->skip_sync = false;
    $user->update(['first_name' => 'Updated Name']);

    Queue::assertPushed(
        PushCustomerUserToTenantsJob::class,
        fn (PushCustomerUserToTenantsJob $job) => $job->customerUserId === $user->id
    );
});

it('does not push when the change arrived from a tenant', function () {
    ['customer' => $customer] = tenantCustomer();

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
    ]);

    Queue::fake();

    $user->update(['first_name' => 'Updated Name']);

    Queue::assertNotPushed(PushCustomerUserToTenantsJob::class);
});

it('queues an archive push when a customer user is soft deleted', function () {
    ['customer' => $customer] = tenantCustomer();

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => false,
    ]);

    Queue::fake();

    $user->scheduleDelete();

    Queue::assertPushed(
        PushCustomerUserToTenantsJob::class,
        fn (PushCustomerUserToTenantsJob $job) => $job->customerUserId === $user->id
            && $job->operation === PushOperation::Archive
    );
});

it('queues a restore push when a tombstoned customer user is brought back', function () {
    ['customer' => $customer] = tenantCustomer();

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => false,
    ]);
    $user->scheduleDelete();

    Queue::fake();

    CustomerUser::withTrashed()->find($user->id)->clearDeleteSchedule();

    Queue::assertPushed(
        PushCustomerUserToTenantsJob::class,
        fn (PushCustomerUserToTenantsJob $job) => $job->operation === PushOperation::Restore
    );
});

it('does not push anything while sync is disabled', function () {
    config(['user_sync.enabled' => false]);

    ['customer' => $customer] = tenantCustomer();

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => false,
    ]);

    app(TenantUserPusher::class)->upsert($user);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/admin-api/v1/sync/'));
});
