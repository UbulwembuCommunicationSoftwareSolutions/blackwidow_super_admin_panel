<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionDeploymentJob;
use App\Models\CustomerUser;
use App\Models\EnvVariables;
use App\Models\SubscriptionType;
use App\Models\TemplateEnvVariables;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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

it('rejects overview without a token', function () {
    $this->getJson('/api/mcp/overview')->assertStatus(401);
});

it('returns overview aggregates for a sanctum user', function () {
    Queue::fake();
    Sanctum::actingAs(User::factory()->create());

    $typeA = SubscriptionType::factory()->create(['name' => 'Case']);
    $typeB = SubscriptionType::factory()->create(['name' => 'Firearm']);
    $customer = Customer::factory()->create(['company_name' => 'SeatCo', 'max_users' => 1]);
    CustomerUser::factory()->count(2)->create(['customer_id' => $customer->id]);
    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $typeA->id,
        'deployed_at' => now(),
    ]);
    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $typeB->id,
        'deployed_at' => null,
    ]);
    $sub = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $typeA->id,
    ]);
    CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $sub->id,
        'batch_id' => (string) Str::uuid(),
        'position' => 1,
        'job_name' => 'deploy_site',
        'status' => CustomerSubscriptionDeploymentJob::STATUS_FAILED,
    ]);

    $res = $this->getJson('/api/mcp/overview')->assertOk();

    expect($res->json('customers.total'))->toBeGreaterThanOrEqual(1)
        ->and($res->json('subscriptions.total'))->toBeGreaterThanOrEqual(3)
        ->and($res->json('subscriptions.deployed'))->toBeGreaterThanOrEqual(1)
        ->and($res->json('subscriptions.not_deployed'))->toBeGreaterThanOrEqual(1)
        ->and($res->json('seat_utilisation.count'))->toBeGreaterThanOrEqual(1)
        ->and($res->json('deployment_jobs_by_status.failed'))->toBeGreaterThanOrEqual(1);

    $atLimit = collect($res->json('seat_utilisation.customers_at_or_over_limit'))
        ->firstWhere('id', $customer->id);
    expect($atLimit)->not->toBeNull()
        ->and($atLimit['customer_users_count'])->toBe(2)
        ->and($atLimit['max_users'])->toBe(1);
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

it('searches and sorts customers and rejects invalid sort', function () {
    Sanctum::actingAs(User::factory()->create());
    Customer::factory()->create(['company_name' => 'Zebra Ltd']);
    Customer::factory()->create(['company_name' => 'Alpha Ltd']);

    $res = $this->getJson('/api/mcp/customers?search=Alpha&sort=company_name&direction=asc&with_counts=1')
        ->assertOk();
    expect(collect($res->json('data'))->pluck('company_name')->all())->toContain('Alpha Ltd')
        ->and($res->json('data.0'))->toHaveKey('customer_subscriptions_count')
        ->and($res->json('data.0'))->toHaveKey('customer_users_count');

    $this->getJson('/api/mcp/customers?sort=password')
        ->assertStatus(422);
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
    expect($first)->not->toHaveKey('env')
        ->and($first)->not->toHaveKey('database_password');
});

it('filters customer subscriptions by search and deployed flag', function () {
    Sanctum::actingAs(User::factory()->create());
    $customer = Customer::factory()->create();
    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'domain' => 'findme.example.com',
        'app_name' => 'FindMe',
        'deployed_at' => now(),
    ]);
    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'domain' => 'other.example.com',
        'deployed_at' => null,
    ]);

    $res = $this->getJson("/api/mcp/customer-subscriptions?customer_id={$customer->id}&search=findme&deployed=1")
        ->assertOk();
    expect(collect($res->json('data'))->pluck('domain')->all())->toBe(['findme.example.com']);
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
    expect($r->json('data'))->not->toHaveKey('env')
        ->not->toHaveKey('database_password');
    $id = $r->json('data.id');
    $this->putJson("/api/mcp/customer-subscriptions/{$id}", ['app_name' => 'MCP App'])->assertOk();
    $this->deleteJson("/api/mcp/customer-subscriptions/{$id}")->assertOk();
});

it('rejects customer users without a token', function () {
    $this->getJson('/api/mcp/customer-users')->assertStatus(401);
});

it('lists customer users without password or sync_hash', function () {
    Queue::fake();
    Sanctum::actingAs(User::factory()->create());
    $customer = Customer::factory()->create();
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'agent@example.com',
        'sync_hash' => 'secret-hash',
        'password' => bcrypt('secret'),
    ]);

    $res = $this->getJson("/api/mcp/customer-users?customer_id={$customer->id}&search=agent")
        ->assertOk();
    $row = collect($res->json('data'))->firstWhere('id', $user->id);
    expect($row)->not->toBeNull()
        ->and($row)->not->toHaveKey('password')
        ->and($row)->not->toHaveKey('sync_hash')
        ->and($row)->not->toHaveKey('remember_token');

    $show = $this->getJson("/api/mcp/customer-users/{$user->id}")->assertOk();
    expect($show->json('data'))->not->toHaveKey('password')
        ->and($show->json('data'))->not->toHaveKey('sync_hash');
});

it('rejects deployment jobs without a token', function () {
    $this->getJson('/api/mcp/deployment-jobs')->assertStatus(401);
});

it('lists deployment jobs and hides forge_log unless include_log', function () {
    Sanctum::actingAs(User::factory()->create());
    $sub = CustomerSubscription::factory()->create();
    $job = CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $sub->id,
        'batch_id' => (string) Str::uuid(),
        'position' => 1,
        'job_name' => 'deploy_site',
        'status' => CustomerSubscriptionDeploymentJob::STATUS_FAILED,
        'forge_log' => 'huge forge log contents',
        'error_message' => 'boom',
    ]);

    $list = $this->getJson("/api/mcp/deployment-jobs?customer_subscription_id={$sub->id}&status=failed")
        ->assertOk();
    $row = collect($list->json('data'))->firstWhere('id', $job->id);
    expect($row)->not->toHaveKey('forge_log')
        ->and($row['error_message'])->toBe('boom');

    $show = $this->getJson("/api/mcp/deployment-jobs/{$job->id}")->assertOk();
    expect($show->json('data'))->not->toHaveKey('forge_log');

    $withLog = $this->getJson("/api/mcp/deployment-jobs/{$job->id}?include_log=1")->assertOk();
    expect($withLog->json('data.forge_log'))->toBe('huge forge log contents');
});

it('rejects env-diff without a token', function () {
    $sub = CustomerSubscription::factory()->create();
    $this->getJson("/api/mcp/customer-subscriptions/{$sub->id}/env-diff")->assertStatus(401);
});

it('compares subscription env and detects missing keys and value mismatches', function () {
    Sanctum::actingAs(User::factory()->create());
    $st = SubscriptionType::factory()->create();
    TemplateEnvVariables::factory()->create([
        'subscription_type_id' => $st->id,
        'key' => 'APP_NAME',
        'value' => 'Template',
    ]);
    TemplateEnvVariables::factory()->create([
        'subscription_type_id' => $st->id,
        'key' => 'APP_KEY',
        'value' => '',
    ]);

    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => $st->id,
        'env' => "APP_NAME=Wrong\nEXTRA=1\n",
    ]);
    EnvVariables::create([
        'customer_subscription_id' => $sub->id,
        'key' => 'APP_NAME',
        'value' => 'Configured',
    ]);
    EnvVariables::create([
        'customer_subscription_id' => $sub->id,
        'key' => 'EXTRA_KEY',
        'value' => 'only-in-rows',
    ]);

    $res = $this->getJson("/api/mcp/customer-subscriptions/{$sub->id}/env-diff")
        ->assertOk();

    expect($res->json('data.missing_keys'))->toContain('APP_KEY')
        ->and($res->json('data.extra_keys'))->toContain('EXTRA_KEY')
        ->and(collect($res->json('data.value_mismatches'))->pluck('key')->all())->toContain('APP_NAME')
        ->and(collect($res->json('data.value_mismatches'))->firstWhere('key', 'APP_NAME'))->not->toHaveKey('configured');

    $withValues = $this->getJson("/api/mcp/customer-subscriptions/{$sub->id}/env-diff?include_values=1")
        ->assertOk();
    $mismatch = collect($withValues->json('data.value_mismatches'))->firstWhere('key', 'APP_NAME');
    expect($mismatch['configured'])->toBe('Configured')
        ->and($mismatch['blob'])->toBe('Wrong');
});
