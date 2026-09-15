<?php

use App\Jobs\PushCustomerUserToTenantsJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Http::fake([
        '*' => Http::response(['success' => true], 200),
    ]);
});

function echoLoopSetup(): array
{
    $customer = Customer::factory()->create(['token' => 'echo-token']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-echo.example.test',
    ]);
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'echo@example.test',
        'first_name' => 'Echo',
        'last_name' => 'User',
        'console_access' => true,
        'skip_sync' => true,
    ]);

    return compact('customer', 'subscription', 'user');
}

it('does not push back to the tenant that sent the update', function () {
    ['subscription' => $subscription, 'user' => $user] = echoLoopSetup();

    Queue::fake();

    $this->withToken('echo-token')->postJson('/api/update-user', [
        'app_url' => $subscription->url,
        'super_admin_user_id' => $user->id,
        'email' => $user->email_address,
        'first_name' => 'Updated',
        'last_name' => 'FromCms',
        'console_access' => true,
        'cms_updated_at' => now()->addMinute()->toIso8601String(),
    ])->assertSuccessful();

    Queue::assertNotPushed(PushCustomerUserToTenantsJob::class);

    expect($user->fresh()->first_name)->toBe('Updated')
        ->and($user->fresh()->skip_sync)->toBeTrue();
});

it('does not push back on the canonical upsert endpoint either', function () {
    ['subscription' => $subscription, 'user' => $user] = echoLoopSetup();

    Queue::fake();

    $this->withToken('echo-token')->postJson('/api/v1/sync/users', [
        'app_url' => $subscription->url,
        'origin' => 'cms',
        'user' => [
            'super_admin_user_id' => $user->id,
            'cms_user_id' => 501,
            'email' => $user->email_address,
            'first_name' => 'Canonical',
            'console_access' => true,
        ],
    ])->assertSuccessful();

    Queue::assertNotPushed(PushCustomerUserToTenantsJob::class);

    expect($user->fresh()->first_name)->toBe('Canonical')
        ->and($user->fresh()->cms_user_id)->toBe(501);
});

it('still pushes to the tenant when an admin edits without skip_sync', function () {
    ['user' => $user] = echoLoopSetup();

    Queue::fake();

    $user->skip_sync = false;
    $user->first_name = 'AdminEdit';
    $user->save();

    Queue::assertPushed(
        PushCustomerUserToTenantsJob::class,
        fn (PushCustomerUserToTenantsJob $job) => $job->customerUserId === $user->id
    );
});
