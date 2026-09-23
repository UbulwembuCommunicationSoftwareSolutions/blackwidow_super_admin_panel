<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use App\Services\UserSync\TenantUserPusher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('posts lms users to the hub with super_admin_customer_id and lms sync token', function (): void {
    config(['services.lms.sync_token' => 'lms-hub-token']);

    SubscriptionType::factory()->create(['id' => SubscriptionType::LMS_TYPE_ID, 'name' => 'LMS']);

    $customer = Customer::factory()->create([
        'token' => 'customer-token',
        'company_name' => 'Acme Academy',
    ]);

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => SubscriptionType::LMS_TYPE_ID,
        'url' => 'https://lms-hub.example.test',
    ]);

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'lms-admin@example.test',
        'lms_access' => false,
        'skip_sync' => true,
    ]);
    $user->forceFill(['lms_access' => true])->saveQuietly();

    Http::fake([
        'https://lms-hub.example.test/admin-api/v1/sync/users' => Http::response([
            'success' => true,
            'user' => ['cms_user_id' => 55],
        ], 200),
    ]);

    app(TenantUserPusher::class)->upsert($user);

    Http::assertSent(function ($request) use ($customer) {
        if ($request->url() !== 'https://lms-hub.example.test/admin-api/v1/sync/users') {
            return false;
        }

        return $request->hasHeader('Authorization', 'Bearer lms-hub-token')
            && $request['user']['super_admin_customer_id'] === $customer->id
            && $request['user']['lms_access'] === true;
    });

    expect($user->fresh()->cms_user_id)->toBe(55);
});

it('posts lms users to the configured hub without an lms subscription', function (): void {
    config([
        'services.lms.sync_token' => 'lms-hub-token',
        'services.lms.hub_url' => 'https://lms-hub.example.test',
    ]);

    Http::fake([
        'https://lms-hub.example.test/*' => Http::response([
            'success' => true,
            'user' => ['cms_user_id' => 77],
        ], 200),
    ]);

    $customer = Customer::factory()->create([
        'token' => 'customer-token',
        'company_name' => 'Acme Academy',
    ]);

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'lms-admin@example.test',
        'lms_access' => false,
        'is_system_admin' => true,
        'skip_sync' => true,
    ]);

    app(TenantUserPusher::class)->upsert($user);

    Http::assertSent(function ($request) use ($customer) {
        return $request->url() === 'https://lms-hub.example.test/admin-api/v1/sync/users'
            && $request->hasHeader('Authorization', 'Bearer lms-hub-token')
            && $request['user']['super_admin_customer_id'] === $customer->id
            && $request['user']['is_system_admin'] === true;
    });
});

it('skips lms push when user has no lms access', function (): void {
    config(['services.lms.sync_token' => 'lms-hub-token']);

    SubscriptionType::factory()->create(['id' => SubscriptionType::LMS_TYPE_ID, 'name' => 'LMS']);

    $customer = Customer::factory()->create(['token' => 'customer-token']);

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => SubscriptionType::LMS_TYPE_ID,
        'url' => 'https://lms-hub.example.test',
    ]);

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'lms_access' => false,
        'is_system_admin' => false,
        'skip_sync' => true,
    ]);

    Http::fake();

    app(TenantUserPusher::class)->upsert($user);

    Http::assertNothingSent();
});
