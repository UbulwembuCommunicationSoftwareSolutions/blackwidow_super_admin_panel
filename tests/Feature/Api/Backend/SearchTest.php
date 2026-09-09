<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\NginxTemplate;
use App\Models\SubscriptionType;
use App\Models\TemplateEnvVariables;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

// The CustomerSubscription observer reaches out to Forge on create.
beforeEach(function () {
    Http::fake();
    Queue::fake();
});

it('rejects search without a token', function () {
    $this->getJson('/api/backend/search?q=acme')->assertUnauthorized();
});

it('requires a term of at least two characters', function () {
    actingAsBackendUser();

    $this->getJson('/api/backend/search')->assertStatus(422)->assertJsonValidationErrors('q');
    $this->getJson('/api/backend/search?q=a')->assertStatus(422)->assertJsonValidationErrors('q');
});

it('groups matches by resource with a title and subtitle', function () {
    actingAsBackendUser();

    $customer = Customer::factory()->create(['company_name' => 'Acme Holdings']);
    $type = SubscriptionType::factory()->create(['name' => 'Acme Storefront']);
    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $type->id,
        'url' => 'acme.example.com',
    ]);

    $groups = collect($this->getJson('/api/backend/search?q=acme')->assertOk()->json('data'))
        ->keyBy('type');

    expect($groups->keys())->toContain('customer', 'subscription', 'subscription-type')
        ->and($groups['customer']['results'][0]['title'])->toBe('Acme Holdings')
        ->and($groups['subscription']['results'][0]['title'])->toBe('acme.example.com')
        ->and($groups['subscription']['results'][0]['subtitle'])->toBe('Acme Holdings');
});

it('omits groups the operator cannot view rather than refusing the search', function () {
    // Everything except the customers group.
    actingAsBackendUser(collect(backendShieldPermissions())
        ->reject(fn ($p) => $p === 'ViewAny:Customer')
        ->values()
        ->all());

    Customer::factory()->create(['company_name' => 'Hidden Holdings']);
    SubscriptionType::factory()->create(['name' => 'Hidden Storefront']);

    $types = collect($this->getJson('/api/backend/search?q=hidden')->assertOk()->json('data'))
        ->pluck('type');

    expect($types)->not->toContain('customer')
        ->and($types)->toContain('subscription-type');
});

it('returns no groups when nothing matches', function () {
    actingAsBackendUser();

    Customer::factory()->create(['company_name' => 'Acme Holdings']);

    expect($this->getJson('/api/backend/search?q=zzzznomatch')->assertOk()->json('data'))->toBe([]);
});

it('caps each group at five results and flags the overflow', function () {
    actingAsBackendUser();

    foreach (range(1, 7) as $i) {
        NginxTemplate::query()->create([
            'name' => 'shared-template',
            'server_id' => 1000 + $i,
            'template_id' => $i,
        ]);
    }

    $group = collect($this->getJson('/api/backend/search?q=shared-template')->assertOk()->json('data'))
        ->firstWhere('type', 'nginx-template');

    expect($group['results'])->toHaveCount(5)
        ->and($group['has_more'])->toBeTrue();
});

it('finds a template env variable by key and names its product', function () {
    actingAsBackendUser();

    $type = SubscriptionType::factory()->create(['name' => 'Storefront']);
    TemplateEnvVariables::factory()->create([
        'subscription_type_id' => $type->id,
        'key' => 'STRIPE_SECRET',
    ]);

    $group = collect($this->getJson('/api/backend/search?q=stripe')->assertOk()->json('data'))
        ->firstWhere('type', 'template-env-variable');

    expect($group['results'][0]['title'])->toBe('STRIPE_SECRET')
        ->and($group['results'][0]['subtitle'])->toBe('Storefront');
});
