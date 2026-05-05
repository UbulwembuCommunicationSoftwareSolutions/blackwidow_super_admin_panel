<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;

it('returns cms url for customer matching responder host', function () {
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    SubscriptionType::factory()->create(['id' => 3, 'name' => 'Responder']);

    $customer = Customer::factory()->create();

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 3,
        'url' => 'https://responder.acme.test/',
    ]);

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms.acme.test/',
    ]);

    $response = $this->getJson('/api/customer_cms_url?customer_api_url='.urlencode('https://responder.acme.test'));

    $response->assertSuccessful()
        ->assertJson([
            'cms_url' => 'https://cms.acme.test',
        ]);
});

it('normalizes trailing slash on cms url', function () {
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    SubscriptionType::factory()->create(['id' => 3, 'name' => 'Responder']);

    $customer = Customer::factory()->create();

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 3,
        'url' => 'https://app.acme.test/responder',
    ]);

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms.acme.test///',
    ]);

    $response = $this->getJson('/api/customer_cms_url?customer_api_url='.urlencode('https://app.acme.test'));

    $response->assertSuccessful()
        ->assertJson([
            'cms_url' => 'https://cms.acme.test',
        ]);
});

it('returns 404 when no subscription matches host', function () {
    $response = $this->getJson('/api/customer_cms_url?customer_api_url='.urlencode('https://unknown.example.test'));

    $response->assertNotFound()
        ->assertJsonFragment(['error' => 'No subscription found for this host']);
});

it('returns 404 when customer has no cms subscription', function () {
    SubscriptionType::factory()->create(['id' => 3, 'name' => 'Responder']);

    $customer = Customer::factory()->create();

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 3,
        'url' => 'https://only-responder.acme.test/',
    ]);

    $response = $this->getJson('/api/customer_cms_url?customer_api_url='.urlencode('https://only-responder.acme.test'));

    $response->assertNotFound()
        ->assertJsonFragment(['error' => 'No CMS subscription found for this customer']);
});

it('returns 422 when customer_api_url is missing', function () {
    $response = $this->getJson('/api/customer_cms_url');

    $response->assertStatus(422)
        ->assertJsonFragment(['error' => 'customer_api_url parameter is required']);
});

it('returns 422 when host cannot be parsed', function () {
    $response = $this->getJson('/api/customer_cms_url?customer_api_url=not-a-valid-url');

    $response->assertStatus(422)
        ->assertJsonFragment(['error' => 'Could not determine host from customer_api_url']);
});
