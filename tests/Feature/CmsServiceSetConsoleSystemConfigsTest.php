<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use App\Services\CMSService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('pushes system configs without dumping the response', function () {
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);
    Log::spy();

    $customer = Customer::factory()->create([
        'token' => 'config-token',
        'docket_description' => 'Cases',
        'task_description' => 'Jobs',
    ]);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-config.example.test',
    ]);
    $subscription->setRelation('customer', $customer->fresh());

    (new CMSService)->setConsoleSystemConfigs($subscription);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://cms-config.example.test/admin-api/set-levels'
            && $request->hasHeader('Authorization', 'Bearer config-token')
            && $request['docket_description'] === 'Cases';
    });

    Log::shouldHaveReceived('info')->withArgs(function ($message) {
        return $message === 'CMS system config sync succeeded';
    });
});

it('throws when system config sync fails so deployment can mark the step failed', function () {
    Http::fake([
        'https://cms-config.example.test/admin-api/set-levels' => Http::response(['error' => 'nope'], 500),
        '*' => Http::response(['ok' => true], 200),
    ]);
    Log::spy();

    $customer = Customer::factory()->create(['token' => 'config-token']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-config.example.test',
    ]);
    $subscription->setRelation('customer', $customer->fresh());

    expect(fn () => (new CMSService)->setConsoleSystemConfigs($subscription))
        ->toThrow(RuntimeException::class);

    Log::shouldHaveReceived('warning')->withArgs(function ($message) {
        return $message === 'CMS system config sync failed';
    });
});
