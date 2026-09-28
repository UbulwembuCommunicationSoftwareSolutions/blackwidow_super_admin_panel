<?php

use App\Jobs\PushCustomerUserPermissionsJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\ProductPermission;
use App\Models\SubscriptionType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Cache::flush();
    config(['user_sync.enabled' => false]);

    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    SubscriptionType::factory()->create(['id' => 3, 'name' => 'Responder']);

    $this->customer = Customer::factory()->create(['token' => 'tenant-token']);
    $this->subscription = CustomerSubscription::factory()->create([
        'customer_id' => $this->customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms.permissions.test',
    ]);
});

function fakeCmsCatalog(array $permissions = []): void
{
    Http::fake([
        'https://cms.permissions.test/admin-api/v1/sync/permissions' => Http::response([
            'guard' => 'web',
            'permissions' => $permissions ?: [
                ['name' => 'view docket', 'group' => 'Dockets', 'sub_group' => 'General'],
                ['name' => 'edit docket', 'group' => 'Dockets', 'sub_group' => 'General'],
                ['name' => 'view task', 'group' => 'Tasks', 'sub_group' => 'Board'],
                ['name' => 'access super admin', 'group' => null, 'sub_group' => null],
            ],
        ]),
    ]);
}

function makeClientUser(Customer $customer, array $attributes = []): CustomerUser
{
    return CustomerUser::factory()->create(array_merge([
        'customer_id' => $customer->id,
        'is_system_admin' => false,
        'console_access' => true,
        'skip_sync' => true,
    ], $attributes));
}

it('pulls the tenant catalog, groups it and mirrors it into product_permissions', function () {
    fakeCmsCatalog();
    actingAsCustomerAdmin($this->customer);

    $response = $this->getJson("/api/backend/customer-subscriptions/{$this->subscription->id}/permissions")
        ->assertOk()
        ->assertJsonPath('data.product', 'console')
        ->assertJsonPath('data.guard', 'web')
        ->assertJsonPath('data.source', 'tenant')
        ->assertJsonCount(4, 'data.permissions');

    $groups = collect($response->json('data.groups'))->keyBy('name');
    expect($groups->keys()->all())->toEqualCanonicalizing(['Dockets', 'Tasks', 'General'])
        ->and($groups['Dockets']['sub_groups'][0]['permissions'])->toBe(['edit docket', 'view docket']);

    expect(ProductPermission::query()->forProduct('console')->active()->count())->toBe(4);

    Http::assertSent(fn ($request) => $request->url() === 'https://cms.permissions.test/admin-api/v1/sync/permissions'
        && $request->hasHeader('Authorization', 'Bearer tenant-token'));
});

it('serves the cached catalog until a refresh is requested and deactivates names the tenant dropped', function () {
    Http::fake([
        'https://cms.permissions.test/*' => Http::sequence()
            ->push(['guard' => 'web', 'permissions' => [['name' => 'view docket'], ['name' => 'edit docket']]])
            ->push(['guard' => 'web', 'permissions' => [['name' => 'view docket', 'group' => 'Dockets', 'sub_group' => 'General']]]),
    ]);
    actingAsCustomerAdmin($this->customer);
    $url = "/api/backend/customer-subscriptions/{$this->subscription->id}/permissions";

    $this->getJson($url)->assertOk();
    $this->getJson($url)->assertOk()->assertJsonPath('data.source', 'cache');
    Http::assertSentCount(1);

    $this->getJson($url.'?refresh=1')->assertOk()->assertJsonPath('data.source', 'tenant')->assertJsonCount(1, 'data.permissions');

    expect(ProductPermission::query()->forProduct('console')->where('name', 'edit docket')->value('is_active'))->toBeFalse();
});

it('falls back to the stored catalog when the tenant is unreachable', function () {
    ProductPermission::factory()->create(['product' => 'console', 'name' => 'view docket', 'group_name' => 'Dockets']);
    Http::fake(['*' => Http::response('down', 503)]);
    actingAsCustomerAdmin($this->customer);

    $this->getJson("/api/backend/customer-subscriptions/{$this->subscription->id}/permissions")
        ->assertOk()
        ->assertJsonPath('data.source', 'stored')
        ->assertJsonPath('data.permissions.0.name', 'view docket')
        ->assertJsonPath('data.error', 'Tenant permission catalog returned HTTP 503');
});

it('rejects subscriptions whose app does not support permission management', function () {
    $responder = CustomerSubscription::factory()->create([
        'customer_id' => $this->customer->id,
        'subscription_type_id' => 3,
    ]);
    actingAsCustomerAdmin($this->customer);

    $this->getJson("/api/backend/customer-subscriptions/{$responder->id}/permissions")->assertUnprocessable();
});

it('returns the matrix of the customer users with their grants for the subscription', function () {
    fakeCmsCatalog();
    $admin = actingAsCustomerAdmin($this->customer, ['first_name' => 'Aaron']);
    $member = makeClientUser($this->customer, ['first_name' => 'Bella']);
    $noAccess = makeClientUser($this->customer, ['first_name' => 'Carl', 'console_access' => false]);
    makeClientUser(Customer::factory()->create(), ['first_name' => 'Outsider']);

    $this->getJson("/api/backend/customer-subscriptions/{$this->subscription->id}/permissions")->assertOk();
    $this->putJson("/api/backend/customer-subscriptions/{$this->subscription->id}/users/{$member->id}/permissions", [
        'permissions' => ['view docket'],
    ])->assertOk();

    $users = collect($this->getJson("/api/backend/customer-subscriptions/{$this->subscription->id}/permission-matrix")
        ->assertOk()
        ->assertJsonPath('data.subscription.product', 'console')
        ->json('data.users'))->keyBy('id');

    expect($users->keys()->all())->toEqualCanonicalizing([$admin->id, $member->id, $noAccess->id])
        ->and($users[$member->id]['permissions'])->toBe(['view docket'])
        ->and($users[$member->id]['has_access'])->toBeTrue()
        ->and($users[$noAccess->id]['has_access'])->toBeFalse()
        ->and($users[$admin->id]['is_system_admin'])->toBeTrue();
});

it('lets a customer admin replace a user\'s grants and queues the tenant push', function () {
    fakeCmsCatalog();
    actingAsCustomerAdmin($this->customer);
    $member = makeClientUser($this->customer);
    $base = "/api/backend/customer-subscriptions/{$this->subscription->id}";

    $this->getJson("{$base}/permissions")->assertOk();

    $this->putJson("{$base}/users/{$member->id}/permissions", ['permissions' => ['view docket', 'view task']])
        ->assertOk()
        ->assertJsonPath('data.permissions', ['view docket', 'view task']);

    $this->putJson("{$base}/users/{$member->id}/permissions", ['permissions' => ['edit docket']])
        ->assertOk()
        ->assertJsonPath('data.permissions', ['edit docket']);

    expect($member->subscriptionPermissionNames($this->subscription))->toBe(['edit docket']);

    $this->putJson("{$base}/users/{$member->id}/permissions", ['permissions' => []])
        ->assertOk()
        ->assertJsonPath('data.permissions', []);

    Queue::assertPushed(PushCustomerUserPermissionsJob::class, fn ($job) => $job->customerUserId === $member->id
        && $job->customerSubscriptionId === $this->subscription->id);
    Queue::assertPushed(PushCustomerUserPermissionsJob::class, 3);
});

it('keeps grants separate per subscription of the same product', function () {
    Http::fake([
        '*' => Http::response(['guard' => 'web', 'permissions' => [['name' => 'view docket'], ['name' => 'view task']]]),
    ]);
    actingAsCustomerAdmin($this->customer);
    $member = makeClientUser($this->customer);
    $second = CustomerSubscription::factory()->create([
        'customer_id' => $this->customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-two.permissions.test',
    ]);

    $this->getJson("/api/backend/customer-subscriptions/{$this->subscription->id}/permissions")->assertOk();
    $this->putJson("/api/backend/customer-subscriptions/{$this->subscription->id}/users/{$member->id}/permissions", ['permissions' => ['view docket']])->assertOk();
    $this->putJson("/api/backend/customer-subscriptions/{$second->id}/users/{$member->id}/permissions", ['permissions' => ['view task']])->assertOk();

    expect($member->subscriptionPermissionNames($this->subscription))->toBe(['view docket'])
        ->and($member->subscriptionPermissionNames($second))->toBe(['view task']);
});

it('rejects permission names that are not in the subscription catalog', function () {
    fakeCmsCatalog();
    actingAsCustomerAdmin($this->customer);
    $member = makeClientUser($this->customer);
    $base = "/api/backend/customer-subscriptions/{$this->subscription->id}";

    $this->getJson("{$base}/permissions")->assertOk();

    $this->putJson("{$base}/users/{$member->id}/permissions", ['permissions' => ['launch rockets']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('permissions');

    $this->putJson("{$base}/users/{$member->id}/permissions", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('permissions');

    $this->putJson("{$base}/users/{$member->id}/permissions", ['permissions' => ['view docket', 'view docket']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('permissions.0');

    Queue::assertNotPushed(PushCustomerUserPermissionsJob::class);
});

it('stops a customer admin from managing other customers or system admins', function () {
    fakeCmsCatalog();
    $other = Customer::factory()->create();
    $otherSubscription = CustomerSubscription::factory()->create([
        'customer_id' => $other->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms.other.test',
    ]);
    $outsider = makeClientUser($other);
    $admin = actingAsCustomerAdmin($this->customer);
    $peerAdmin = makeClientUser($this->customer, ['is_system_admin' => true]);
    $base = "/api/backend/customer-subscriptions/{$this->subscription->id}";

    $this->getJson("/api/backend/customer-subscriptions/{$otherSubscription->id}/permissions")->assertForbidden();
    $this->getJson("/api/backend/customer-subscriptions/{$otherSubscription->id}/permission-matrix")->assertForbidden();
    $this->putJson("{$base}/users/{$outsider->id}/permissions", ['permissions' => []])->assertForbidden();
    $this->putJson("{$base}/users/{$peerAdmin->id}/permissions", ['permissions' => []])->assertForbidden();
    $this->putJson("{$base}/users/{$admin->id}/permissions", ['permissions' => []])->assertForbidden();

    Queue::assertNotPushed(PushCustomerUserPermissionsJob::class);
});

it('lets staff with update rights manage any customer and forbids staff without them', function () {
    fakeCmsCatalog();
    $member = makeClientUser($this->customer);
    $base = "/api/backend/customer-subscriptions/{$this->subscription->id}";

    actingAsBackendForbidden();
    $this->getJson("{$base}/permissions")->assertForbidden();

    actingAsBackendUser(['View:CustomerSubscription', 'ViewAny:CustomerUser', 'Update:CustomerUser']);
    $this->getJson("{$base}/permissions")->assertOk();
    $this->putJson("{$base}/users/{$member->id}/permissions", ['permissions' => ['view docket']])->assertOk();
});
