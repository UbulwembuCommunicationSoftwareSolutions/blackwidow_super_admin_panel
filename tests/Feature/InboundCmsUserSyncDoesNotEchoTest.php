<?php

use App\Jobs\StartUserSyncJob;
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
        '*' => Http::response(['ok' => true], 200),
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

it('does not dispatch StartUserSyncJob or call syncUsers on update-user from CMS', function () {
    ['subscription' => $subscription, 'user' => $user] = echoLoopSetup();

    Queue::fake();
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    $this->withToken('echo-token')->postJson('/api/update-user', [
        'app_url' => $subscription->url,
        'super_admin_user_id' => $user->id,
        'email' => $user->email_address,
        'first_name' => 'Updated',
        'last_name' => 'FromCms',
        'console_access' => true,
        'cms_updated_at' => now()->addMinute()->toIso8601String(),
    ])->assertSuccessful();

    Queue::assertNotPushed(StartUserSyncJob::class);

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/admin-api/sync-users');
    });

    expect($user->fresh()->first_name)->toBe('Updated')
        ->and($user->fresh()->skip_sync)->toBeTrue();
});

it('still syncs users to CMS when an admin updates without skip_sync', function () {
    ['user' => $user] = echoLoopSetup();

    Http::fake([
        'https://cms-echo.example.test/admin-api/sync-users' => Http::response(['ok' => true], 200),
        '*' => Http::response(['ok' => true], 200),
    ]);

    $user->skip_sync = false;
    $user->first_name = 'AdminEdit';
    $user->save();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://cms-echo.example.test/admin-api/sync-users';
    });
});
