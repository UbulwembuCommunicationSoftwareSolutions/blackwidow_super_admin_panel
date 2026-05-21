<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Returns a SubscriptionType whose id is NOT 1 so the CustomerSubscriptionObserver
 * (which only acts on subscription_type_id === 1 and would fire a real HTTP call) skips it.
 */
function nonCmsSubscriptionType(): SubscriptionType
{
    SubscriptionType::factory()->create();

    return SubscriptionType::factory()->create();
}

it('rejects crm health without a token', function () {
    $this->getJson('/api/crm/health')
        ->assertStatus(401);
});

it('rejects crm health for a token without the crm ability', function () {
    Sanctum::actingAs(User::factory()->create(), ['mcp']);

    $this->getJson('/api/crm/health')
        ->assertStatus(403);
});

it('returns crm health for a sanctum user with crm ability', function () {
    Sanctum::actingAs(User::factory()->create(), ['crm']);

    $this->getJson('/api/crm/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonStructure(['app', 'environment', 'status']);
});

it('returns subscription types for a crm user', function () {
    Sanctum::actingAs(User::factory()->create(), ['crm']);
    SubscriptionType::factory()->create(['name' => 'Type A']);

    $this->getJson('/api/crm/subscription-types')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Type A');
});

it('lists customers without secret fields for a crm user', function () {
    Sanctum::actingAs(User::factory()->create(), ['crm']);
    Customer::factory()->create(['company_name' => 'Acme Corp']);

    $res = $this->getJson('/api/crm/customers?per_page=5')
        ->assertOk();
    $first = $res->json('data.0');
    expect($first)->not->toHaveKey('s3_secret')
        ->and($first)->not->toHaveKey('token')
        ->and($first)->not->toHaveKey('google_api_key')
        ->and($first['company_name'])->toBe('Acme Corp');
});

it('shows a single customer without secret fields for a crm user', function () {
    Sanctum::actingAs(User::factory()->create(), ['crm']);
    $customer = Customer::factory()->create(['company_name' => 'Acme Corp']);

    $res = $this->getJson("/api/crm/customers/{$customer->id}")
        ->assertOk();
    $data = $res->json('data');
    expect($data)->not->toHaveKey('s3_secret')
        ->and($data)->not->toHaveKey('token')
        ->and($data['company_name'])->toBe('Acme Corp');
});

it('lists customer subscriptions without env blob or database password for a crm user', function () {
    Sanctum::actingAs(User::factory()->create(), ['crm']);
    $st = nonCmsSubscriptionType();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => $st->id,
        'env' => '{"SECRET":"x"}',
    ]);

    $res = $this->getJson("/api/crm/customer-subscriptions?per_page=5&customer_id={$sub->customer_id}")
        ->assertOk();
    $first = collect($res->json('data'))->firstWhere('id', $sub->id);
    expect($first)->not->toHaveKey('env')
        ->and($first)->not->toHaveKey('database_password');
});

it('shows a customer subscription with env hidden by default and visible with include_env', function () {
    Sanctum::actingAs(User::factory()->create(), ['crm']);
    $st = nonCmsSubscriptionType();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => $st->id,
        'env' => '{"FOO":"bar"}',
    ]);

    $default = $this->getJson("/api/crm/customer-subscriptions/{$sub->id}")->assertOk();
    expect($default->json('data'))->not->toHaveKey('env')
        ->and($default->json('data'))->not->toHaveKey('database_password');

    $withEnv = $this->getJson("/api/crm/customer-subscriptions/{$sub->id}?include_env=1")->assertOk();
    expect($withEnv->json('data.env'))->toBe('{"FOO":"bar"}')
        ->and($withEnv->json('data'))->not->toHaveKey('database_password');
});

it('can create update delete a customer without secret fields in response', function () {
    Sanctum::actingAs(User::factory()->create(), ['crm']);
    $r = $this->postJson('/api/crm/customers', [
        'company_name' => 'CrmCo',
        'max_users' => 10,
    ])->assertCreated();
    $id = $r->json('data.id');
    expect($r->json('data'))->not->toHaveKey('s3_secret')
        ->and($r->json('data'))->not->toHaveKey('token');

    $this->putJson("/api/crm/customers/{$id}", ['company_name' => 'CrmCo2'])
        ->assertOk()
        ->assertJsonPath('data.company_name', 'CrmCo2');

    $this->deleteJson("/api/crm/customers/{$id}")->assertOk();
});

it('ignores attempts to write customer secret fields via crm api', function () {
    Sanctum::actingAs(User::factory()->create(), ['crm']);
    $r = $this->postJson('/api/crm/customers', [
        'company_name' => 'CrmCo',
        'token' => 'attacker-supplied-token',
        's3_secret' => 'attacker-supplied-secret',
    ])->assertCreated();

    $id = $r->json('data.id');
    $customer = Customer::query()->findOrFail($id);
    expect($customer->s3_secret)->toBeNull();
});

it('can create update delete a customer subscription', function () {
    Sanctum::actingAs(User::factory()->create(), ['crm']);
    $st = SubscriptionType::factory()->create();
    $cust = Customer::factory()->create();
    $r = $this->postJson('/api/crm/customer-subscriptions', [
        'url' => 'https://example.com',
        'domain' => 'example.com',
        'database_name' => 'crm_test_db',
        'subscription_type_id' => $st->id,
        'customer_id' => $cust->id,
    ])->assertCreated();
    expect($r->json('data'))->not->toHaveKey('env')
        ->not->toHaveKey('database_password');
    $id = $r->json('data.id');
    $this->putJson("/api/crm/customer-subscriptions/{$id}", ['app_name' => 'CRM App'])
        ->assertOk()
        ->assertJsonPath('data.app_name', 'CRM App');
    $this->deleteJson("/api/crm/customer-subscriptions/{$id}")->assertOk();
});

it('filters customer subscriptions by customer_id and subscription_type_id', function () {
    Sanctum::actingAs(User::factory()->create(), ['crm']);
    $stA = SubscriptionType::factory()->create();
    $stB = SubscriptionType::factory()->create();
    $custA = Customer::factory()->create();
    $custB = Customer::factory()->create();

    $matching = CustomerSubscription::factory()->create([
        'customer_id' => $custA->id,
        'subscription_type_id' => $stA->id,
    ]);
    CustomerSubscription::factory()->create([
        'customer_id' => $custB->id,
        'subscription_type_id' => $stA->id,
    ]);
    CustomerSubscription::factory()->create([
        'customer_id' => $custA->id,
        'subscription_type_id' => $stB->id,
    ]);

    $res = $this->getJson("/api/crm/customer-subscriptions?customer_id={$custA->id}&subscription_type_id={$stA->id}")
        ->assertOk();
    $ids = collect($res->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$matching->id]);
});
