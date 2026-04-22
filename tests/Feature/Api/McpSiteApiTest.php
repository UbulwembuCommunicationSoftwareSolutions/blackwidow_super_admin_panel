<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\SubscriptionType;
use App\Models\TemplateEnvVariables;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects mcp health without a token', function () {
    $this->getJson('/api/mcp/health')
        ->assertStatus(401);
});

it('returns mcp health for a sanctum user', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/mcp/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonStructure(['app', 'environment', 'status']);
});

it('returns subscription types for a sanctum user', function () {
    Sanctum::actingAs(User::factory()->create());
    SubscriptionType::factory()->create(['name' => 'Type A']);

    $this->getJson('/api/mcp/subscription-types')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Type A');
});

it('returns template env variables for a sanctum user', function () {
    Sanctum::actingAs(User::factory()->create());
    $st = SubscriptionType::factory()->create();
    TemplateEnvVariables::factory()->create([
        'subscription_type_id' => $st->id,
        'key' => 'APP_NAME',
    ]);

    $this->getJson("/api/mcp/template-env-variables?subscription_type_id={$st->id}")
        ->assertOk()
        ->assertJsonPath('data.0.key', 'APP_NAME');
});

it('returns env variables for a subscription for a sanctum user', function () {
    Sanctum::actingAs(User::factory()->create());
    $sub = CustomerSubscription::factory()->create();
    EnvVariables::create([
        'key' => 'APP_DEBUG',
        'value' => 'false',
        'customer_subscription_id' => $sub->id,
    ]);

    $this->getJson("/api/mcp/env-variables?customer_subscription_id={$sub->id}")
        ->assertOk()
        ->assertJsonPath('data.0.key', 'APP_DEBUG');
});

it('lists customers without secret fields for a sanctum user', function () {
    Sanctum::actingAs(User::factory()->create());
    Customer::factory()->create(['company_name' => 'Acme Corp']);

    $res = $this->getJson('/api/mcp/customers?per_page=5')
        ->assertOk();
    $first = $res->json('data.0');
    expect($first)->not->toHaveKey('s3_secret')
        ->and($first)->not->toHaveKey('token')
        ->and($first['company_name'])->toBe('Acme Corp');
});

it('lists customer subscriptions without env blob for a sanctum user', function () {
    Sanctum::actingAs(User::factory()->create());
    $st = SubscriptionType::factory()->create();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => $st->id,
        'env' => '{"SECRET":"x"}',
    ]);

    $res = $this->getJson("/api/mcp/customer-subscriptions?per_page=5&customer_id={$sub->customer_id}")
        ->assertOk();
    $first = collect($res->json('data'))->firstWhere('id', $sub->id);
    expect($first)->not->toHaveKey('env');
});

it('can create and delete a template env variable', function () {
    Sanctum::actingAs(User::factory()->create());
    $st = SubscriptionType::factory()->create();

    $r = $this->postJson('/api/mcp/template-env-variables', [
        'subscription_type_id' => $st->id,
        'key' => 'MCP_TEST_KEY',
        'value' => 'x',
        'requires_manual_fill' => false,
    ])->assertCreated();

    $id = $r->json('data.id');
    $this->getJson("/api/mcp/template-env-variables/{$id}")->assertOk()->assertJsonPath('data.key', 'MCP_TEST_KEY');
    $this->putJson("/api/mcp/template-env-variables/{$id}", ['value' => 'y'])->assertOk()->assertJsonPath('data.value', 'y');
    $this->deleteJson("/api/mcp/template-env-variables/{$id}")->assertOk();
});

it('can create update delete an env variable row', function () {
    Sanctum::actingAs(User::factory()->create());
    $sub = CustomerSubscription::factory()->create();
    $r = $this->postJson('/api/mcp/env-variables', [
        'customer_subscription_id' => $sub->id,
        'key' => 'MCP_FOO',
        'value' => 'bar',
    ])->assertCreated();
    $id = $r->json('data.id');
    $this->putJson("/api/mcp/env-variables/{$id}", ['value' => 'baz'])->assertOk()->assertJsonPath('data.value', 'baz');
    $this->deleteJson("/api/mcp/env-variables/{$id}")->assertOk();
});

it('can create update delete a customer without secret fields in response', function () {
    Sanctum::actingAs(User::factory()->create());
    $r = $this->postJson('/api/mcp/customers', [
        'company_name' => 'McpCo',
        'max_users' => 10,
    ])->assertCreated();
    $id = $r->json('data.id');
    expect($r->json('data'))->not->toHaveKey('s3_secret');
    $this->putJson("/api/mcp/customers/{$id}", ['company_name' => 'McpCo2'])->assertOk();
    $this->deleteJson("/api/mcp/customers/{$id}")->assertOk();
});

it('can create a customer subscription', function () {
    Sanctum::actingAs(User::factory()->create());
    $st = SubscriptionType::factory()->create();
    $cust = Customer::factory()->create();
    $r = $this->postJson('/api/mcp/customer-subscriptions', [
        'url' => 'https://example.com',
        'domain' => 'example.com',
        'database_name' => 'mcp_test_db',
        'subscription_type_id' => $st->id,
        'customer_id' => $cust->id,
    ])->assertCreated();
    expect($r->json('data'))->not->toHaveKey('env');
    $id = $r->json('data.id');
    $this->putJson("/api/mcp/customer-subscriptions/{$id}", ['app_name' => 'MCP App'])->assertOk();
    $this->deleteJson("/api/mcp/customer-subscriptions/{$id}")->assertOk();
});
