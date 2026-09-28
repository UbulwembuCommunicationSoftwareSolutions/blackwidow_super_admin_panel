<?php

use App\Jobs\PushCustomerUserPermissionsJob;
use App\Jobs\PushCustomerUserToTenantsJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\ProductPermission;
use App\Models\SubscriptionType;
use App\Models\UserSyncLog;
use App\Services\UserSync\TenantUserPusher;
use App\Support\UserSync\PushOperation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config(['user_sync.enabled' => true]);

    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    SubscriptionType::factory()->create(['id' => 2, 'name' => 'Firearm']);
    SubscriptionType::factory()->create(['id' => SubscriptionType::LMS_TYPE_ID, 'name' => 'LMS']);

    $this->customer = Customer::factory()->create(['token' => 'tenant-token']);
    $this->subscription = CustomerSubscription::factory()->create([
        'customer_id' => $this->customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms.push.test',
    ]);
    $this->user = CustomerUser::factory()->create([
        'customer_id' => $this->customer->id,
        'email_address' => 'push@tenant.test',
        'cms_user_id' => 42,
        'is_system_admin' => false,
        'skip_sync' => true,
    ]);
});

function grantSubscriptionPermissions(CustomerUser $user, CustomerSubscription $subscription, array $names): void
{
    foreach ($names as $name) {
        $permission = ProductPermission::factory()->create([
            'product' => $subscription->permissionProduct(),
            'name' => $name,
        ]);
        $user->subscriptionPermissions()->attach($permission->id, ['customer_subscription_id' => $subscription->id]);
    }
}

it('posts the granted names to the tenant users/permissions endpoint and logs the outcome', function () {
    grantSubscriptionPermissions($this->user, $this->subscription, ['view docket', 'edit docket']);
    Http::fake([
        'https://cms.push.test/admin-api/v1/sync/users/permissions' => Http::response([
            'success' => true,
            'applied' => ['edit docket', 'view docket'],
            'ignored' => [],
        ]),
    ]);

    app(TenantUserPusher::class)->permissions($this->user, $this->subscription);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://cms.push.test/admin-api/v1/sync/users/permissions'
            && $request->hasHeader('Authorization', 'Bearer tenant-token')
            && $request['origin'] === 'super_admin'
            && $request['user']['super_admin_user_id'] === $this->user->id
            && $request['user']['cms_user_id'] === 42
            && $request['user']['email'] === 'push@tenant.test'
            && $request['permissions'] === ['edit docket', 'view docket'];
    });

    $log = UserSyncLog::query()->latest('id')->first();
    expect($log->status)->toBe('success')
        ->and($log->sync_data['operation'])->toBe('permissions')
        ->and($log->sync_data['customer_subscription_id'])->toBe($this->subscription->id)
        ->and($log->sync_data['applied'])->toBe(['edit docket', 'view docket']);
});

it('records a skip instead of failing when the tenant does not know the user yet', function () {
    grantSubscriptionPermissions($this->user, $this->subscription, ['view docket']);
    Http::fake(['*' => Http::response(['message' => 'User not found'], 404)]);

    app(TenantUserPusher::class)->permissions($this->user, $this->subscription);

    expect(UserSyncLog::query()->latest('id')->value('status'))->toBe('skipped');
});

it('throws and logs a failure when the tenant errors so the job retries', function () {
    Http::fake(['*' => Http::response('boom', 500)]);

    expect(fn () => app(TenantUserPusher::class)->permissions($this->user, $this->subscription))
        ->toThrow(RuntimeException::class);

    expect(UserSyncLog::query()->latest('id')->value('status'))->toBe('failed');
});

it('pushes LMS grants to the shared hub with the LMS sync token and lms user id', function () {
    config(['services.lms.hub_url' => 'https://lms-hub.push.test', 'services.lms.sync_token' => 'lms-token']);
    $lms = CustomerSubscription::factory()->create([
        'customer_id' => $this->customer->id,
        'subscription_type_id' => SubscriptionType::LMS_TYPE_ID,
        'url' => 'https://legacy-lms.push.test',
    ]);
    $this->user->forceFill(['lms_user_id' => 77])->saveQuietly();
    grantSubscriptionPermissions($this->user, $lms, ['courses.view']);
    Http::fake(['*' => Http::response(['success' => true, 'applied' => ['courses.view'], 'ignored' => []])]);

    app(TenantUserPusher::class)->permissions($this->user, $lms);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://lms-hub.push.test/admin-api/v1/sync/users/permissions'
        && $request->hasHeader('Authorization', 'Bearer lms-token')
        && $request['user']['cms_user_id'] === 77
        && $request['permissions'] === ['courses.view']);
});

it('does not push to subscriptions whose app lacks the permission contract', function () {
    SubscriptionType::factory()->create(['id' => 3, 'name' => 'Responder']);
    $responder = CustomerSubscription::factory()->create([
        'customer_id' => $this->customer->id,
        'subscription_type_id' => 3,
        'url' => 'https://responder.push.test',
    ]);
    Http::fake();

    app(TenantUserPusher::class)->permissions($this->user, $responder);

    Http::assertNothingSent();
});

it('runs the queued permission job through the pusher', function () {
    grantSubscriptionPermissions($this->user, $this->subscription, ['view docket']);
    Http::fake(['*' => Http::response(['success' => true, 'applied' => ['view docket'], 'ignored' => []])]);

    (new PushCustomerUserPermissionsJob($this->user->id, $this->subscription->id))->handle(app(TenantUserPusher::class));

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/admin-api/v1/sync/users/permissions'));
});

it('follows a user upsert with a permissions push when the user holds grants', function () {
    grantSubscriptionPermissions($this->user, $this->subscription, ['view docket']);
    Http::fake(['*' => Http::response(['success' => true, 'user' => ['cms_user_id' => 42]])]);

    (new PushCustomerUserToTenantsJob($this->user->id, PushOperation::Upsert))->handle(app(TenantUserPusher::class));

    Queue::assertPushed(PushCustomerUserToTenantsJob::class, fn ($job) => $job->operation === PushOperation::Permissions);

    (new PushCustomerUserToTenantsJob($this->user->id, PushOperation::Permissions))->handle(app(TenantUserPusher::class));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://cms.push.test/admin-api/v1/sync/users/permissions'
        && $request['permissions'] === ['view docket']);
});

it('does not queue a permissions push after an upsert for a user without grants', function () {
    Http::fake(['*' => Http::response(['success' => true, 'user' => ['cms_user_id' => 42]])]);

    (new PushCustomerUserToTenantsJob($this->user->id, PushOperation::Upsert))->handle(app(TenantUserPusher::class));

    Queue::assertNotPushed(PushCustomerUserToTenantsJob::class, fn ($job) => $job->operation === PushOperation::Permissions);
});

it('includes the subscription grants in the legacy user-import response', function () {
    $firearm = CustomerSubscription::factory()->create([
        'customer_id' => $this->customer->id,
        'subscription_type_id' => 2,
        'url' => 'https://firearm.push.test',
    ]);
    $other = CustomerUser::factory()->create([
        'customer_id' => $this->customer->id,
        'skip_sync' => true,
    ]);
    grantSubscriptionPermissions($this->user, $firearm, ['view_any_firearm::issue']);
    grantSubscriptionPermissions($this->user, $this->subscription, ['view docket']);

    $users = collect($this->withToken('tenant-token')
        ->postJson('/api/user-import', ['app_url' => 'https://firearm.push.test'])
        ->assertOk()
        ->json('data'))->keyBy('id');

    expect($users[$this->user->id]['permissions'])->toBe(['view_any_firearm::issue'])
        ->and($users[$other->id])->not->toHaveKey('permissions');
});
