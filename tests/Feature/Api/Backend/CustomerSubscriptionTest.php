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

it('rewrites firearm-module hosts to firearm when creating a firearm subscription', function () {
    actingAsBackendUser();
    SubscriptionType::factory()->create(['id' => 2, 'name' => 'Firearm Module']);
    $customer = Customer::factory()->create();

    $create = $this->postJson('/api/backend/customer-subscriptions', [
        'url' => 'https://demo.firearm-module.blackwidow.org.za',
        'domain' => 'demo.firearm-module.blackwidow.org.za',
        'app_name' => 'Demo Firearm',
        'database_name' => 'demo_firearm_module_blackwidow',
        'subscription_type_id' => 2,
        'customer_id' => $customer->id,
    ])->assertCreated();

    expect($create->json('data.url'))->toBe('https://demo.firearm.blackwidow.org.za')
        ->and($create->json('data.domain'))->toBe('demo.firearm.blackwidow.org.za');

    $this->assertDatabaseHas('customer_subscriptions', [
        'id' => $create->json('data.id'),
        'url' => 'https://demo.firearm.blackwidow.org.za',
        'domain' => 'demo.firearm.blackwidow.org.za',
    ]);
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

it('retries a failed deployment job', function () {
    actingAsBackendUser();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
    ]);
    $job = CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $sub->id,
        'batch_id' => 'retry-batch',
        'position' => 0,
        'job_name' => 'create_site',
        'status' => CustomerSubscriptionDeploymentJob::STATUS_FAILED,
        'error_message' => 'Unauthenticated.',
    ]);

    $this->mock(SiteDeploymentScheduler::class, function ($mock) {
        $mock->shouldReceive('retryDeploymentJob')->once()->andReturn('retry-batch');
    });

    $this->postJson("/api/backend/customer-subscriptions/{$sub->id}/deployment-jobs/{$job->id}/retry")
        ->assertOk()
        ->assertJsonPath('data.batch_id', 'retry-batch')
        ->assertJsonPath('data.deployment_job_id', $job->id);
});

it('runs a deployment job alone', function () {
    actingAsBackendUser();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
    ]);
    $job = CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $sub->id,
        'batch_id' => 'original-batch',
        'position' => 2,
        'job_name' => 'create_site',
        'status' => CustomerSubscriptionDeploymentJob::STATUS_FAILED,
        'error_message' => 'boom',
    ]);

    $this->mock(SiteDeploymentScheduler::class, function ($mock) {
        $mock->shouldReceive('requeueDeploymentJobAlone')->once()->andReturn('alone-batch');
    });

    $this->postJson("/api/backend/customer-subscriptions/{$sub->id}/deployment-jobs/{$job->id}/run-alone")
        ->assertOk()
        ->assertJsonPath('data.batch_id', 'alone-batch')
        ->assertJsonPath('data.deployment_job_id', $job->id);
});

it('returns 404 when retrying a deployment job from another subscription', function () {
    actingAsBackendUser();
    $typeId = SubscriptionType::factory()->create(['project_type' => 'static'])->id;
    $sub = CustomerSubscription::factory()->create(['subscription_type_id' => $typeId]);
    $other = CustomerSubscription::factory()->create(['subscription_type_id' => $typeId]);
    $job = CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $other->id,
        'batch_id' => 'other-batch',
        'position' => 0,
        'job_name' => 'create_site',
        'status' => CustomerSubscriptionDeploymentJob::STATUS_FAILED,
        'error_message' => 'boom',
    ]);

    $this->postJson("/api/backend/customer-subscriptions/{$sub->id}/deployment-jobs/{$job->id}/retry")
        ->assertNotFound();
});

it('rejects retrying a running deployment job', function () {
    actingAsBackendUser();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
    ]);
    $job = CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $sub->id,
        'batch_id' => 'running-batch',
        'position' => 0,
        'job_name' => 'create_site',
        'status' => CustomerSubscriptionDeploymentJob::STATUS_RUNNING,
        'started_at' => now(),
    ]);

    $this->postJson("/api/backend/customer-subscriptions/{$sub->id}/deployment-jobs/{$job->id}/retry")
        ->assertStatus(422);
});

it('forbids retrying a deployment job without Update:CustomerSubscription', function () {
    actingAsBackendForbidden();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
    ]);
    $job = CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $sub->id,
        'batch_id' => 'forbidden-batch',
        'position' => 0,
        'job_name' => 'create_site',
        'status' => CustomerSubscriptionDeploymentJob::STATUS_FAILED,
        'error_message' => 'boom',
    ]);

    $this->postJson("/api/backend/customer-subscriptions/{$sub->id}/deployment-jobs/{$job->id}/retry")
        ->assertForbidden();
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

it('bulk deploys the selected subscriptions and reports skipped ids', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create(['project_type' => 'static']);
    $a = CustomerSubscription::factory()->create(['subscription_type_id' => $type->id]);
    $b = CustomerSubscription::factory()->create(['subscription_type_id' => $type->id]);

    $this->postJson('/api/backend/customer-subscriptions/bulk/deploy', ['ids' => [$a->id, $b->id, 999999]])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('queued', [$a->id, $b->id])
        ->assertJsonPath('skipped.0.id', 999999)
        ->assertJsonPath('skipped.0.reason', 'Not found');

    Queue::assertPushed(DeploySite::class, 2);
    Queue::assertPushed(DeploySite::class, fn (DeploySite $job) => $job->customerSubscriptionId === $a->id);
    Queue::assertPushed(DeploySite::class, fn (DeploySite $job) => $job->customerSubscriptionId === $b->id);
});

it('validates bulk deploy requires ids', function () {
    actingAsBackendUser();

    $this->postJson('/api/backend/customer-subscriptions/bulk/deploy', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ids']);

    $this->postJson('/api/backend/customer-subscriptions/bulk/deploy', ['ids' => ['abc']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ids.0']);

    Queue::assertNothingPushed();
});

it('forbids bulk deploy without Update:CustomerSubscription', function () {
    actingAsBackendUser(['View:CustomerSubscription']);
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
    ]);

    $this->postJson('/api/backend/customer-subscriptions/bulk/deploy', ['ids' => [$sub->id]])
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('forbids bulk deploy for a customer admin and queues nothing', function () {
    $mine = Customer::factory()->create();
    $other = Customer::factory()->create();
    actingAsCustomerAdmin($mine);
    $type = SubscriptionType::factory()->create(['project_type' => 'static']);
    $own = CustomerSubscription::factory()->create(['customer_id' => $mine->id, 'subscription_type_id' => $type->id]);
    $foreign = CustomerSubscription::factory()->create(['customer_id' => $other->id, 'subscription_type_id' => $type->id]);

    $this->postJson('/api/backend/customer-subscriptions/bulk/deploy', ['ids' => [$own->id, $foreign->id]])
        ->assertForbidden();

    Queue::assertNotPushed(DeploySite::class);
});
