<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;

it('returns absolute logo urls using customer_api_url and responder subscription', function () {
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    SubscriptionType::factory()->create(['id' => 3, 'name' => 'Responder']);

    $customer = Customer::factory()->create();

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms.acme.test/',
        'logo_1' => 'brand/cms-logo.png',
    ]);

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 3,
        'url' => 'https://responder.acme.test/',
        'logo_1' => 'brand/responder-logo.png',
        'logo_2' => null,
        'logo_3' => '',
    ]);

    $response = $this->get('/customer_logos?customer_api_url='.urlencode('https://responder.acme.test'));

    $response->assertSuccessful();

    $expected = rtrim(config('app.url'), '/').'/storage/brand/responder-logo.png';

    expect($response->json('logo_1'))->toBe($expected)
        ->and($response->json('logo_2'))->toBeNull()
        ->and($response->json('logo_3'))->toBeNull();
});

it('prefers subscription_type_id responder when multiple subscriptions share the same host', function () {
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    SubscriptionType::factory()->create(['id' => 3, 'name' => 'Responder']);

    $customer = Customer::factory()->create();

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://portal.shared.test/cms',
        'logo_1' => 'brand/cms.png',
    ]);

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 3,
        'url' => 'https://portal.shared.test/responder',
        'logo_1' => 'brand/responder.png',
    ]);

    $response = $this->get('/customer_logos?customer_api_url='.urlencode('https://portal.shared.test/mobile'));

    $response->assertSuccessful();

    expect($response->json('logo_1'))->toBe(rtrim(config('app.url'), '/').'/storage/brand/responder.png');
});

it('returns empty json object when no subscription matches', function () {
    $response = $this->get('/customer_logos?customer_api_url='.urlencode('https://unknown.example.test'));

    $response->assertSuccessful();
    expect($response->getContent())->toBe('{}');
});

it('returns empty json object when customer_api_url has no host', function () {
    $response = $this->get('/customer_logos?customer_api_url='.urlencode('not-a-valid-url'));

    $response->assertSuccessful();
    expect($response->getContent())->toBe('{}');
});

it('resolves logos via legacy customer_url with trailing slash mismatch', function () {
    SubscriptionType::factory()->create(['id' => 3, 'name' => 'Responder']);

    $customer = Customer::factory()->create();

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 3,
        'url' => 'https://legacy.acme.test/',
        'logo_1' => 'icons/legacy.png',
    ]);

    $response = $this->get('/customer_logos?customer_url='.urlencode('https://legacy.acme.test'));

    $response->assertSuccessful();

    expect($response->json('logo_1'))->toBe(rtrim(config('app.url'), '/').'/storage/icons/legacy.png');
});
