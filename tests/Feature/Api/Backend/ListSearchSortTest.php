<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\ForgeServer;
use App\Models\SubscriptionType;
use App\Models\TemplateEnvVariables;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::fake();
    Queue::fake();
});

it('filters customers by search across allowlisted columns', function () {
    actingAsBackendUser();

    Customer::factory()->create(['company_name' => 'Blackwidow Security']);
    Customer::factory()->create(['company_name' => 'Metro Stock Control']);

    $response = $this->getJson('/api/backend/customers?search=blackwidow')->assertOk();

    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.company_name'))->toBe('Blackwidow Security');
});

it('matches customer search case-insensitively and on a partial term', function () {
    actingAsBackendUser();

    Customer::factory()->create(['company_name' => 'Coastal Guarding Group']);

    expect($this->getJson('/api/backend/customers?search=GUARD')->assertOk()->json('total'))->toBe(1);
});

it('returns no customers when the search matches nothing', function () {
    actingAsBackendUser();

    Customer::factory()->create(['company_name' => 'Blackwidow Security']);

    expect($this->getJson('/api/backend/customers?search=nothingmatchesthis')->assertOk()->json('total'))->toBe(0);
});

it('never searches the customer api token', function () {
    actingAsBackendUser();

    $customer = Customer::factory()->create(['company_name' => 'Token Holder']);
    $customer->forceFill(['token' => 'super-secret-token'])->save();

    expect($this->getJson('/api/backend/customers?search=super-secret-token')->assertOk()->json('total'))->toBe(0);
});

it('sorts customers ascending and descending', function () {
    actingAsBackendUser();

    Customer::factory()->create(['company_name' => 'Alpha']);
    Customer::factory()->create(['company_name' => 'Zulu']);

    $asc = $this->getJson('/api/backend/customers?sort=company_name&direction=asc')->assertOk();
    $desc = $this->getJson('/api/backend/customers?sort=company_name&direction=desc')->assertOk();

    expect($asc->json('data.0.company_name'))->toBe('Alpha')
        ->and($desc->json('data.0.company_name'))->toBe('Zulu');
});

it('defaults to ascending when a sort is given without a direction', function () {
    actingAsBackendUser();

    Customer::factory()->create(['company_name' => 'Zulu']);
    Customer::factory()->create(['company_name' => 'Alpha']);

    expect($this->getJson('/api/backend/customers?sort=company_name')->assertOk()->json('data.0.company_name'))
        ->toBe('Alpha');
});

it('rejects a sort column that is not on the allowlist', function () {
    actingAsBackendUser();

    $this->getJson('/api/backend/customers?sort=token')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sort']);
});

it('rejects an unknown sort direction', function () {
    actingAsBackendUser();

    $this->getJson('/api/backend/customers?sort=company_name&direction=sideways')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['direction']);
});

it('keeps the default id ordering when no sort is requested', function () {
    actingAsBackendUser();

    $zulu = Customer::factory()->create(['company_name' => 'Zulu']);
    $alpha = Customer::factory()->create(['company_name' => 'Alpha']);

    expect($this->getJson('/api/backend/customers')->assertOk()->json('data.0.id'))
        ->toBe($zulu->id)
        ->and($alpha->id)->toBeGreaterThan($zulu->id);
});

it('keeps env variables ordered by key by default', function () {
    actingAsBackendUser();

    $customer = createCustomerWithSubscriptions();
    $subscription = $customer->customerSubscriptions()->first();

    foreach (['ZED_KEY', 'APP_KEY', 'MID_KEY'] as $key) {
        $this->postJson('/api/backend/env-variables', [
            'customer_subscription_id' => $subscription->id,
            'key' => $key,
            'value' => 'x',
        ])->assertCreated();
    }

    expect($this->getJson('/api/backend/env-variables')->assertOk()->json('data.0.key'))->toBe('APP_KEY');
});

it('keeps template env variables ordered by type then key by default', function () {
    actingAsBackendUser();

    $type = SubscriptionType::factory()->create();
    TemplateEnvVariables::factory()->create(['subscription_type_id' => $type->id, 'key' => 'ZED']);
    TemplateEnvVariables::factory()->create(['subscription_type_id' => $type->id, 'key' => 'ABLE']);

    expect($this->getJson('/api/backend/template-env-variables')->assertOk()->json('data.0.key'))->toBe('ABLE');
});

it('searches subscriptions through the related type and customer', function () {
    actingAsBackendUser();

    $customer = Customer::factory()->create(['company_name' => 'Findable Holdings']);
    $type = SubscriptionType::factory()->create(['name' => 'responder']);
    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $type->id,
        'url' => 'https://unrelated.example.com',
    ]);

    expect($this->getJson('/api/backend/customer-subscriptions?search=Findable')->assertOk()->json('total'))->toBe(1)
        ->and($this->getJson('/api/backend/customer-subscriptions?search=responder')->assertOk()->json('total'))->toBe(1);
});

it('searches forge servers by numeric forge id without breaking text search', function () {
    actingAsBackendUser();

    ForgeServer::query()->create(['forge_server_id' => 998877, 'name' => 'alpha-server', 'ip_address' => '10.0.0.1']);
    ForgeServer::query()->create(['forge_server_id' => 112233, 'name' => 'beta-server', 'ip_address' => '10.0.0.2']);

    expect($this->getJson('/api/backend/forge-servers?search=998877')->assertOk()->json('total'))->toBe(1)
        ->and($this->getJson('/api/backend/forge-servers?search=beta')->assertOk()->json('total'))->toBe(1);
});

it('searches admin users by name and email', function () {
    actingAsBackendUser();

    User::factory()->create(['name' => 'Naledi Khumalo', 'email' => 'naledi@aims.work']);

    expect($this->getJson('/api/backend/users?search=Khumalo')->assertOk()->json('total'))->toBe(1)
        ->and($this->getJson('/api/backend/users?search=naledi@aims.work')->assertOk()->json('total'))->toBe(1);
});

it('rejects a forge server id that is already taken', function () {
    actingAsBackendUser();

    ForgeServer::query()->create(['forge_server_id' => 4242, 'name' => 'existing']);

    $this->postJson('/api/backend/forge-servers', ['forge_server_id' => 4242, 'name' => 'duplicate'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['forge_server_id']);
});

it('lets a forge server keep its own id on update', function () {
    actingAsBackendUser();

    $server = ForgeServer::query()->create(['forge_server_id' => 5150, 'name' => 'keeper']);

    $this->putJson("/api/backend/forge-servers/{$server->id}", [
        'forge_server_id' => 5150,
        'name' => 'renamed',
    ])->assertOk()->assertJsonPath('data.name', 'renamed');
});

it('summarises template env variable counts across the whole filtered set', function () {
    actingAsBackendUser();

    $type = SubscriptionType::factory()->create();
    TemplateEnvVariables::factory()->count(4)->create([
        'subscription_type_id' => $type->id,
        'requires_manual_fill' => false,
    ]);
    TemplateEnvVariables::factory()->count(3)->create([
        'subscription_type_id' => $type->id,
        'requires_manual_fill' => true,
    ]);

    // Page size of two proves the counts are not scoped to the current page.
    $this->getJson("/api/backend/template-env-variables?subscription_type_id={$type->id}&per_page=2")
        ->assertOk()
        ->assertJsonPath('summary.total', 7)
        ->assertJsonPath('summary.manual', 3)
        ->assertJsonCount(2, 'data');
});

it('narrows the template env variable summary to the search term', function () {
    actingAsBackendUser();

    $type = SubscriptionType::factory()->create();
    TemplateEnvVariables::factory()->create([
        'subscription_type_id' => $type->id,
        'key' => 'STRIPE_SECRET',
        'requires_manual_fill' => true,
    ]);
    TemplateEnvVariables::factory()->create([
        'subscription_type_id' => $type->id,
        'key' => 'MAIL_HOST',
        'requires_manual_fill' => false,
    ]);

    $this->getJson('/api/backend/template-env-variables?search=STRIPE')
        ->assertOk()
        ->assertJsonPath('summary.total', 1)
        ->assertJsonPath('summary.manual', 1);
});
