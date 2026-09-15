<?php

use App\Jobs\SiteDeployment\DeploySite;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionDeploymentJob;
use App\Models\ForgeServer;
use App\Models\SubscriptionType;
use App\Services\DomainDnsService;
use App\Services\SiteDeploymentScheduler;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Http::fake();
    Queue::fake();
});

it('rejects customer subscriptions without a token', function () {
    $this->getJson('/api/backend/customer-subscriptions')->assertUnauthorized();
});

it('forbids customer subscriptions without Shield permissions', function () {
    actingAsBackendForbidden();

    $this->getJson('/api/backend/customer-subscriptions')->assertForbidden();
});

it('returns 404 for a missing customer subscription', function () {
    actingAsBackendUser();

    $this->getJson('/api/backend/customer-subscriptions/999999')->assertNotFound();
});

it('validates customer subscription create', function () {
    actingAsBackendUser();

    $this->postJson('/api/backend/customer-subscriptions', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url', 'domain', 'app_name', 'customer_id', 'subscription_type_id']);
});

it('can crud a customer subscription and hide secrets', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create(['project_type' => 'static']);
    $customer = Customer::factory()->create();

    $create = $this->postJson('/api/backend/customer-subscriptions', [
        'url' => 'https://example.com',
        'domain' => 'example.com',
        'app_name' => 'Example App',
        'database_name' => 'example_db',
        'subscription_type_id' => $type->id,
        'customer_id' => $customer->id,
        'env' => 'SECRET=1',
    ])->assertCreated();

    $id = $create->json('data.id');
    expect($create->json('data'))->not->toHaveKey('env')
        ->not->toHaveKey('database_password');

    $this->getJson("/api/backend/customer-subscriptions?customer_id={$customer->id}")
        ->assertOk();

    $this->putJson("/api/backend/customer-subscriptions/{$id}", [
        'app_name' => 'Renamed App',
    ])->assertOk()->assertJsonPath('data.app_name', 'Renamed App');

    $this->deleteJson("/api/backend/customer-subscriptions/{$id}")
        ->assertOk()
        ->assertJsonPath('ok', true);

    $this->assertDatabaseMissing('customer_subscriptions', ['id' => $id]);
});

it('dispatches deploy and queues recreate-site when mocked', function () {
    actingAsBackendUser();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
        'forge_site_id' => null,
        'server_id' => 99,
    ]);

    $this->postJson("/api/backend/customer-subscriptions/{$sub->id}/deploy")
        ->assertOk()
        ->assertJsonPath('ok', true);

    Queue::assertPushed(DeploySite::class);

    $this->mock(SiteDeploymentScheduler::class, function ($mock) {
        $mock->shouldReceive('scheduleSiteCreationOnly')->once()->andReturn('batch-123');
    });

    $this->postJson("/api/backend/customer-subscriptions/{$sub->id}/recreate-site")
        ->assertOk()
        ->assertJsonPath('data.batch_id', 'batch-123');
});

it('rejects recreate-site when a forge site already exists', function () {
    actingAsBackendUser();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
        'forge_site_id' => '123',
        'server_id' => 1,
    ]);

    $this->postJson("/api/backend/customer-subscriptions/{$sub->id}/recreate-site")
        ->assertStatus(422);
});

it('rejects pull-env without forge coordinates', function () {
    actingAsBackendUser();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
        'forge_site_id' => null,
        'server_id' => null,
    ]);

    $this->postJson("/api/backend/customer-subscriptions/{$sub->id}/pull-env")
        ->assertStatus(422);
});

it('returns a handled error when generate-logos has no image', function () {
    actingAsBackendUser();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
        'logo_1' => 'missing.png',
    ]);

    $this->postJson("/api/backend/customer-subscriptions/{$sub->id}/generate-logos")
        ->assertStatus(422)
        ->assertJsonStructure(['message']);
});

it('updates the forge server on a subscription', function () {
    actingAsBackendUser();
    $server = ForgeServer::query()->create([
        'forge_server_id' => 4242,
        'name' => 'Test Server',
        'ip_address' => '1.2.3.4',
    ]);
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
        'server_id' => null,
    ]);

    $this->putJson("/api/backend/customer-subscriptions/{$sub->id}/server", [
        'server_id' => $server->forge_server_id,
    ])->assertOk()->assertJsonPath('data.server_id', $server->forge_server_id);
});

it('lists pipeline steps and queues a mocked step', function () {
    actingAsBackendUser();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
        'database_name' => 'pipeline_db',
    ]);

    $this->getJson("/api/backend/customer-subscriptions/{$sub->id}/pipeline-steps")
        ->assertOk()
        ->assertJsonStructure(['data' => [['index', 'job_name', 'parameters']]]);

    $this->mock(SiteDeploymentScheduler::class, function ($mock) {
        $mock->shouldReceive('queueSingleTemplateStep')->once()->andReturn('step-batch');
    });

    $this->postJson("/api/backend/customer-subscriptions/{$sub->id}/pipeline-steps/0")
        ->assertOk()
        ->assertJsonPath('data.batch_id', 'step-batch');
});

it('lists deployment jobs for a subscription', function () {
    actingAsBackendUser();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
    ]);
    CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $sub->id,
        'batch_id' => 'b1',
        'position' => 0,
        'job_name' => 'create_site',
        'status' => CustomerSubscriptionDeploymentJob::STATUS_PENDING,
    ]);

    $this->getJson("/api/backend/customer-subscriptions/{$sub->id}/deployment-jobs")
        ->assertOk()
        ->assertJsonPath('data.0.job_name', 'create_site');
});

it('verifies a domain via DomainDnsService', function () {
    $user = actingAsBackendUser();
    RateLimiter::clear('verify-domain:'.$user->id);

    $this->mock(DomainDnsService::class, function ($mock) {
        $mock->shouldReceive('lookup')
            ->once()
            ->with('example.blackwidow.test')
            ->andReturn([
                'resolves' => true,
                'ips' => ['203.0.113.10', '203.0.113.11'],
            ]);
    });

    $this->postJson('/api/backend/customer-subscriptions/verify-domain', [
        'domain' => 'example.blackwidow.test',
    ])->assertOk()
        ->assertJsonPath('data.domain', 'example.blackwidow.test')
        ->assertJsonPath('data.resolves', true)
        ->assertJsonPath('data.ips.0', '203.0.113.10')
        ->assertJsonPath('data.ips.1', '203.0.113.11');
});

it('returns resolves false when DomainDnsService finds no records', function () {
    $user = actingAsBackendUser();
    RateLimiter::clear('verify-domain:'.$user->id);

    $this->mock(DomainDnsService::class, function ($mock) {
        $mock->shouldReceive('lookup')
            ->once()
            ->with('missing.example')
            ->andReturn([
                'resolves' => false,
                'ips' => [],
            ]);
    });

    $this->postJson('/api/backend/customer-subscriptions/verify-domain', [
        'domain' => 'missing.example',
    ])->assertOk()
        ->assertJsonPath('data.resolves', false)
        ->assertJsonPath('data.ips', []);
});

it('validates verify-domain requires a domain', function () {
    actingAsBackendUser();

    $this->postJson('/api/backend/customer-subscriptions/verify-domain', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['domain']);
});

it('forbids verify-domain without Create:CustomerSubscription', function () {
    actingAsBackendForbidden();

    $this->postJson('/api/backend/customer-subscriptions/verify-domain', [
        'domain' => 'example.com',
    ])->assertForbidden();
});
