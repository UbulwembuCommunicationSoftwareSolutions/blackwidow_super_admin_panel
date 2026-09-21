<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Http::fake();
});

it('attaches the console catalog permission without changing system admin', function () {
    $customer = Customer::factory()->create(['token' => 'catalog-token']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-catalog.example.test',
    ]);

    $this->withToken('catalog-token')->postJson('/api/v1/sync/users', [
        'app_url' => $subscription->url,
        'origin' => 'cms',
        'user' => [
            'cms_user_id' => 15,
            'email' => 'portal@tenant.test',
            'first_name' => 'Portal',
            'is_system_admin' => false,
            'super_admin_panel_access' => true,
            'console_access' => true,
        ],
    ])->assertCreated();

    $user = CustomerUser::firstWhere('email_address', 'portal@tenant.test');

    expect($user->is_system_admin)->toBeFalse()
        ->and($user->productPermissions()->where('product', 'console')->where('name', 'access super admin')->exists())->toBeTrue();
});

it('detaches the grant and leaves other fields alone when the flag is false', function () {
    $customer = Customer::factory()->create(['token' => 'catalog-token']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-catalog.example.test',
    ]);

    $this->withToken('catalog-token')->postJson('/api/v1/sync/users', [
        'app_url' => $subscription->url,
        'origin' => 'cms',
        'user' => [
            'email' => 'keep@tenant.test',
            'is_system_admin' => true,
            'console_access' => true,
            'super_admin_panel_access' => true,
        ],
    ])->assertCreated();

    $this->withToken('catalog-token')->postJson('/api/v1/sync/users', [
        'app_url' => $subscription->url,
        'origin' => 'cms',
        'user' => [
            'email' => 'keep@tenant.test',
            'super_admin_panel_access' => false,
        ],
    ])->assertSuccessful();

    $user = CustomerUser::firstWhere('email_address', 'keep@tenant.test');

    expect($user->is_system_admin)->toBeTrue()
        ->and($user->console_access)->toBeTrue()
        ->and($user->productPermissions)->toHaveCount(0);
});

it('records the firearm catalog permission for a firearm subscription', function () {
    $customer = Customer::factory()->create(['token' => 'catalog-token']);
    SubscriptionType::factory()->create(['id' => 2, 'name' => 'Firearm']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 2,
        'url' => 'https://firearm-catalog.example.test',
    ]);

    $this->withToken('catalog-token')->postJson('/api/v1/sync/users', [
        'app_url' => $subscription->url,
        'origin' => 'firearm',
        'user' => [
            'email' => 'armoury@tenant.test',
            'super_admin_panel_access' => true,
        ],
    ])->assertCreated();

    $user = CustomerUser::firstWhere('email_address', 'armoury@tenant.test');

    expect($user->productPermissions()->where('product', 'firearm')->where('name', 'access super admin')->exists())->toBeTrue()
        ->and($user->productPermissions()->where('product', 'console')->exists())->toBeFalse();
});
