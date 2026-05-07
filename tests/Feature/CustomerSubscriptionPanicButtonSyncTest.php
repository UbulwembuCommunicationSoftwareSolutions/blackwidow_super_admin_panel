<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use App\Services\CMSService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        '*' => Http::response(['message' => 'ok', 'type' => 'success'], 200),
    ]);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
});

it('pushes panic_button_enabled to cms when cms subscription is created', function () {
    $customer = Customer::factory()->create(['token' => 'sync-secret']);
    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms.example.test',
        'panic_button_enabled' => true,
    ]);

    Http::assertSent(function ($request): bool {
        if ($request->url() !== 'https://cms.example.test/admin-api/set-panic-button-enabled') {
            return false;
        }
        if (! $request->hasHeader('Authorization', 'Bearer sync-secret')) {
            return false;
        }
        $data = json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);

        return $data['panic_button_enabled'] === true;
    });
});

it('pushes panic_button_enabled when subscription flag is updated', function () {
    $customer = Customer::factory()->create(['token' => 'sync-secret']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms.example.test',
        'panic_button_enabled' => false,
    ]);

    expect(Http::recorded())->toHaveCount(1);

    $subscription->update(['panic_button_enabled' => true]);

    expect(Http::recorded())->toHaveCount(2);

    /** @var \Illuminate\Http\Client\Request $lastRequest */
    $lastRequest = Http::recorded()[1][0];
    $payload = json_decode($lastRequest->body(), true, 512, JSON_THROW_ON_ERROR);
    expect($payload['panic_button_enabled'])->toBeTrue();
});

it('does not push when subscription is not cms subscription type', function () {
    $apiType = SubscriptionType::factory()->create(['name' => 'API']);
    expect($apiType->id)->not->toBe(1);
    $customer = Customer::factory()->create(['token' => 'sync-secret']);
    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $apiType->id,
        'url' => 'https://api.example.test',
        'panic_button_enabled' => true,
    ]);

    Http::assertNothingSent();
});

it('syncPanicButtonEnabled no-ops for non-cms subscription', function () {
    $subscription = CustomerSubscription::factory()->make([
        'subscription_type_id' => 2,
        'url' => 'https://cms.example.test',
    ]);

    CMSService::syncPanicButtonEnabled($subscription);

    Http::assertNothingSent();
});
