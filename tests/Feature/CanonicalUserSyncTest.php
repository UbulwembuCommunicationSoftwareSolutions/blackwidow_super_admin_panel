<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use App\Services\UserSync\TenantUserPusher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    // Http stubs are matched in registration order, so the tenant sync endpoint
    // has to be declared before the catch-all that absorbs unrelated calls made
    // by observers during setup.
    Http::fake([
        '*/admin-api/v1/sync/users' => Http::response([
            'success' => true,
            'user' => ['cms_user_id' => 777],
        ], 200),
        '*' => Http::response(['success' => true], 200),
    ]);
});

function canonicalTenant(): array
{
    $customer = Customer::factory()->create(['token' => 'canonical-token']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-canonical.example.test',
    ]);

    return compact('customer', 'subscription');
}

function postCanonical(string $path, array $body)
{
    return test()->withToken('canonical-token')->postJson('/api/v1/sync/users'.$path, $body);
}

it('accepts origin lms on a user upsert', function () {
    ['subscription' => $subscription] = canonicalTenant();

    postCanonical('', [
        'app_url' => $subscription->url,
        'origin' => 'lms',
        'user' => [
            'cms_user_id' => 92,
            'email' => 'lms-origin@tenant.test',
            'first_name' => 'Lms',
        ],
    ])->assertCreated()
        ->assertJsonPath('outcome', 'created');
});

it('creates a customer user from a tenant and records both ids', function () {
    ['subscription' => $subscription] = canonicalTenant();

    $response = postCanonical('', [
        'app_url' => $subscription->url,
        'origin' => 'cms',
        'password' => 'tenant-secret-password',
        'user' => [
            'cms_user_id' => 91,
            'email' => 'created@tenant.test',
            'first_name' => 'Created',
            'last_name' => 'FromTenant',
            'console_access' => true,
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('outcome', 'created')
        ->assertJsonPath('user.cms_user_id', 91);

    $user = CustomerUser::firstWhere('email_address', 'created@tenant.test');

    expect($user->cms_user_id)->toBe(91)
        ->and($user->console_access)->toBeTrue()
        ->and($response->json('user.super_admin_user_id'))->toBe($user->id)
        ->and(Hash::check('tenant-secret-password', $user->password))->toBeTrue();
});

it('never returns a password hash on a write', function () {
    ['subscription' => $subscription] = canonicalTenant();

    $response = postCanonical('', [
        'app_url' => $subscription->url,
        'password' => 'tenant-secret-password',
        'user' => [
            'email' => 'nohash@tenant.test',
            'first_name' => 'No',
            'console_access' => true,
        ],
    ]);

    expect($response->json('user'))->not->toHaveKey('password')
        ->not->toHaveKey('password_hash');
});

it('grants access to the tenant the user was created from', function () {
    $customer = Customer::factory()->create(['token' => 'canonical-token']);
    SubscriptionType::factory()->create(['id' => 2, 'name' => 'Firearm']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 2,
        'url' => 'https://firearm-canonical.example.test',
    ]);

    postCanonical('', [
        'app_url' => $subscription->url,
        'password' => 'tenant-secret-password',
        'user' => [
            'email' => 'firearm@tenant.test',
            'first_name' => 'Firearm',
        ],
    ])->assertCreated();

    $user = CustomerUser::firstWhere('email_address', 'firearm@tenant.test');

    expect($user->firearm_access)->toBeTrue()
        ->and($user->console_access)->toBeFalse();
});

it('does not let a stale tenant payload restore a revoked access flag', function () {
    ['subscription' => $subscription, 'customer' => $customer] = canonicalTenant();

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'revoked@tenant.test',
        'console_access' => false,
        'skip_sync' => true,
    ]);

    $response = postCanonical('', [
        'app_url' => $subscription->url,
        'user' => [
            'super_admin_user_id' => $user->id,
            'email' => 'revoked@tenant.test',
            'first_name' => 'Revoked',
            'console_access' => true,
            'updated_at' => now()->subDay()->toIso8601String(),
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('outcome', 'stale');

    expect($user->fresh()->console_access)->toBeFalse();
});

it('keeps our copy when the tenant sends an older record', function () {
    ['subscription' => $subscription, 'customer' => $customer] = canonicalTenant();

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'conflict@tenant.test',
        'first_name' => 'Ours',
        'skip_sync' => true,
    ]);

    $response = postCanonical('', [
        'app_url' => $subscription->url,
        'user' => [
            'super_admin_user_id' => $user->id,
            'email' => 'conflict@tenant.test',
            'first_name' => 'Theirs',
            'updated_at' => now()->subDay()->toIso8601String(),
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('outcome', 'stale')
        ->assertJsonPath('user.first_name', 'Ours');

    expect($user->fresh()->first_name)->toBe('Ours');
});

it('accepts the tenant record when it is newer', function () {
    ['subscription' => $subscription, 'customer' => $customer] = canonicalTenant();

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'newer@tenant.test',
        'first_name' => 'Ours',
        'skip_sync' => true,
    ]);

    postCanonical('', [
        'app_url' => $subscription->url,
        'user' => [
            'super_admin_user_id' => $user->id,
            'email' => 'newer@tenant.test',
            'first_name' => 'Theirs',
            'updated_at' => now()->addMinute()->toIso8601String(),
        ],
    ])->assertSuccessful()->assertJsonPath('outcome', 'updated');

    expect($user->fresh()->first_name)->toBe('Theirs');
});

it('does not change the password on a routine profile sync', function () {
    ['subscription' => $subscription, 'customer' => $customer] = canonicalTenant();

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'keeppass@tenant.test',
        'password' => 'original-password',
        'skip_sync' => true,
    ]);

    postCanonical('', [
        'app_url' => $subscription->url,
        'user' => [
            'super_admin_user_id' => $user->id,
            'email' => 'keeppass@tenant.test',
            'first_name' => 'Renamed',
        ],
    ])->assertSuccessful();

    expect(Hash::check('original-password', $user->fresh()->password))->toBeTrue();
});

it('matches on cms_user_id when the tenant does not know our id', function () {
    ['subscription' => $subscription, 'customer' => $customer] = canonicalTenant();

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'cms_user_id' => 314,
        'email_address' => 'bycms@tenant.test',
        'skip_sync' => true,
    ]);

    postCanonical('', [
        'app_url' => $subscription->url,
        'user' => [
            'cms_user_id' => 314,
            'email' => 'changed@tenant.test',
            'first_name' => 'Renamed',
        ],
    ])->assertSuccessful()->assertJsonPath('outcome', 'updated');

    expect($user->fresh()->email_address)->toBe('changed@tenant.test');
});

it('tombstones and restores through the canonical endpoints', function () {
    ['subscription' => $subscription, 'customer' => $customer] = canonicalTenant();

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'archive@tenant.test',
        'skip_sync' => true,
    ]);

    postCanonical('/archive', [
        'app_url' => $subscription->url,
        'user' => ['super_admin_user_id' => $user->id],
    ])->assertSuccessful()->assertJsonPath('outcome', 'archived');

    $archived = CustomerUser::withTrashed()->find($user->id);
    expect($archived->trashed())->toBeTrue()
        ->and($archived->delete_scheduled)->not->toBeNull();

    postCanonical('/restore', [
        'app_url' => $subscription->url,
        'user' => ['super_admin_user_id' => $user->id],
    ])->assertSuccessful()->assertJsonPath('outcome', 'restored');

    $restored = CustomerUser::withTrashed()->find($user->id);
    expect($restored->trashed())->toBeFalse()
        ->and($restored->delete_scheduled)->toBeNull();
});

it('sets a password through the canonical endpoint', function () {
    ['subscription' => $subscription, 'customer' => $customer] = canonicalTenant();

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'password@tenant.test',
        'password' => 'old-password',
        'skip_sync' => true,
    ]);

    postCanonical('/password', [
        'app_url' => $subscription->url,
        'user' => ['super_admin_user_id' => $user->id],
        'password' => 'brand-new-password',
    ])->assertSuccessful();

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();
});

it('rejects an operation on a user belonging to another customer', function () {
    ['subscription' => $subscription] = canonicalTenant();

    $otherCustomer = Customer::factory()->create(['token' => 'other-token']);
    $foreignUser = CustomerUser::factory()->create([
        'customer_id' => $otherCustomer->id,
        'email_address' => 'foreign@tenant.test',
        'skip_sync' => true,
    ]);

    postCanonical('/archive', [
        'app_url' => $subscription->url,
        'user' => ['super_admin_user_id' => $foreignUser->id],
    ])->assertNotFound();

    expect(CustomerUser::withTrashed()->find($foreignUser->id)->trashed())->toBeFalse();
});

it('requires an identifier to locate a user', function () {
    ['subscription' => $subscription] = canonicalTenant();

    postCanonical('/archive', [
        'app_url' => $subscription->url,
        'user' => ['first_name' => 'Nameless'],
    ])->assertStatus(422);
});

it('rejects unauthenticated canonical requests', function () {
    ['subscription' => $subscription] = canonicalTenant();

    $this->withToken('wrong-token')->postJson('/api/v1/sync/users', [
        'app_url' => $subscription->url,
        'user' => ['email' => 'nope@tenant.test', 'first_name' => 'Nope'],
    ])->assertUnauthorized();
});

it('lists every user including tombstoned ones for reconciliation', function () {
    ['subscription' => $subscription, 'customer' => $customer] = canonicalTenant();

    $live = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'live@tenant.test',
        'skip_sync' => true,
    ]);
    $gone = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'gone@tenant.test',
        'skip_sync' => true,
    ]);
    $gone->scheduleDelete();

    $response = $this->withToken('canonical-token')
        ->getJson('/api/v1/sync/users?app_url='.urlencode($subscription->url));

    $response->assertSuccessful();

    $users = collect($response->json('users'));

    expect($users)->toHaveCount(2)
        ->and($users->firstWhere('super_admin_user_id', $live->id)['delete_scheduled'])->toBeNull()
        ->and($users->firstWhere('super_admin_user_id', $gone->id)['delete_scheduled'])->not->toBeNull()
        ->and($users->firstWhere('super_admin_user_id', $live->id))->toHaveKey('password_hash');
});

it('resolves the exact tenant when one url is a substring of another', function () {
    $customer = Customer::factory()->create(['token' => 'exact-token']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    SubscriptionType::factory()->create(['id' => 2, 'name' => 'Firearm']);

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 2,
        'url' => 'https://firearm.acme.example.test',
    ]);
    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://acme.example.test',
    ]);

    $this->withToken('exact-token')->postJson('/api/v1/sync/users', [
        'app_url' => 'https://acme.example.test',
        'password' => 'tenant-secret-password',
        'user' => [
            'email' => 'exact@tenant.test',
            'first_name' => 'Exact',
        ],
    ])->assertCreated();

    $user = CustomerUser::firstWhere('email_address', 'exact@tenant.test');

    // The console subscription was the exact match, so console access is granted
    // rather than the firearm access a substring match would have given.
    expect($user->console_access)->toBeTrue()
        ->and($user->firearm_access)->toBeFalse();
});

it('learns the tenant user id from the push response', function () {
    ['customer' => $customer] = canonicalTenant();

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'learn@tenant.test',
        'skip_sync' => true,
    ]);

    app(TenantUserPusher::class)->upsert($user);

    expect($user->fresh()->cms_user_id)->toBe(777);
});
