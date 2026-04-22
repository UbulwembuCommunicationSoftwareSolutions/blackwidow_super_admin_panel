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
