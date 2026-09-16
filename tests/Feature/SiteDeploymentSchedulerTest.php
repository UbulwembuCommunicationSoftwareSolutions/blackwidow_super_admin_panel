<?php

use App\Jobs\SendCommandToForgeJob;
use App\Jobs\SiteDeployment\CreateForgeServerDatabaseUserJob;
use App\Jobs\SiteDeployment\CreateSiteOnForgeJob;
use App\Jobs\SiteDeployment\EnsureForgeSiteIdJob;
use App\Jobs\SiteDeployment\ProvisionForgeServerDatabaseJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionDeploymentJob;
use App\Models\SubscriptionType;
use App\Services\DeploymentStepDispatcher;
use App\Services\SiteDeploymentScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('persists deployment steps and dispatches only create site first', function () {
    Queue::fake();

    $type = SubscriptionType::factory()->create(['project_type' => 'static']);
    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => $customer->id,
        'site_deployment_queue_started_at' => null,
        'server_id' => 1,
    ]);

    $batchId = app(SiteDeploymentScheduler::class)->schedule($subscription, true);

    expect($batchId)->not->toBeEmpty();
    $baseSteps = 8;
    $extraSteps = in_array($subscription->subscription_type_id, [1, 2, 9, 10, 11], true) ? 6 : 0;
    expect(CustomerSubscriptionDeploymentJob::query()->where('batch_id', $batchId)->count())->toBe($baseSteps + $extraSteps);

    Queue::assertPushed(CreateSiteOnForgeJob::class, 1);
    Queue::assertNotPushed(ProvisionForgeServerDatabaseJob::class);
    Queue::assertNotPushed(EnsureForgeSiteIdJob::class);
});

it('dispatches ensure forge site after create site completes', function () {
    Queue::fake();

    $type = SubscriptionType::factory()->create(['project_type' => 'static']);
    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => $customer->id,
        'site_deployment_queue_started_at' => null,
        'server_id' => 1,
    ]);

    $batchId = app(SiteDeploymentScheduler::class)->schedule($subscription, true);

    $first = CustomerSubscriptionDeploymentJob::query()
        ->where('batch_id', $batchId)
        ->where('position', 0)
        ->firstOrFail();

    app(DeploymentStepDispatcher::class)->completeAndDispatchNext($first->id);

    Queue::assertPushed(EnsureForgeSiteIdJob::class, 1);
});

it('dispatches provision forge server database before create site when subscription needs forge mysql', function () {
    Queue::fake();

    $type = SubscriptionType::factory()->create(['project_type' => 'php']);
    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => $customer->id,
        'site_deployment_queue_started_at' => null,
        'server_id' => 1,
        'database_name' => 'app_db',
    ]);

    $batchId = app(SiteDeploymentScheduler::class)->schedule($subscription, true);

    expect($batchId)->not->toBeEmpty();
    $baseSteps = 8 + 2;
    $extraSteps = in_array($subscription->subscription_type_id, [1, 2, 9, 10, 11], true) ? 6 : 0;
    expect(CustomerSubscriptionDeploymentJob::query()->where('batch_id', $batchId)->count())->toBe($baseSteps + $extraSteps);

    Queue::assertPushed(ProvisionForgeServerDatabaseJob::class, 1);
    Queue::assertNotPushed(CreateForgeServerDatabaseUserJob::class);
    Queue::assertNotPushed(CreateSiteOnForgeJob::class);

    $first = CustomerSubscriptionDeploymentJob::query()
        ->where('batch_id', $batchId)
        ->where('position', 0)
        ->firstOrFail();

    app(DeploymentStepDispatcher::class)->completeAndDispatchNext($first->id);

    Queue::assertPushed(CreateForgeServerDatabaseUserJob::class, 1);
    Queue::assertNotPushed(CreateSiteOnForgeJob::class);

    $second = CustomerSubscriptionDeploymentJob::query()
        ->where('batch_id', $batchId)
        ->where('position', 1)
        ->firstOrFail();

    app(DeploymentStepDispatcher::class)->completeAndDispatchNext($second->id);

    Queue::assertPushed(CreateSiteOnForgeJob::class, 1);
    Queue::assertPushed(ProvisionForgeServerDatabaseJob::class, 1);
});

it('schedule site creation only persists one or two steps and does not run full pipeline', function () {
    Queue::fake();

    $type = SubscriptionType::factory()->create(['project_type' => 'static']);
    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => $customer->id,
        'site_deployment_queue_started_at' => null,
        'server_id' => 1,
    ]);

    $batchId = app(SiteDeploymentScheduler::class)->scheduleSiteCreationOnly($subscription, true);

    expect($batchId)->not->toBeEmpty();
    expect(CustomerSubscriptionDeploymentJob::query()->where('batch_id', $batchId)->count())->toBe(1);

    Queue::assertPushed(CreateSiteOnForgeJob::class, 1);
    Queue::assertNotPushed(ProvisionForgeServerDatabaseJob::class);
    Queue::assertNotPushed(EnsureForgeSiteIdJob::class);
});

it('schedule site creation only prepends provision when subscription needs forge mysql', function () {
    Queue::fake();

    $type = SubscriptionType::factory()->create(['project_type' => 'php']);
    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => $customer->id,
        'site_deployment_queue_started_at' => null,
        'server_id' => 1,
        'database_name' => 'app_db',
    ]);

    $batchId = app(SiteDeploymentScheduler::class)->scheduleSiteCreationOnly($subscription, true);

    expect(CustomerSubscriptionDeploymentJob::query()->where('batch_id', $batchId)->count())->toBe(3);

    Queue::assertPushed(ProvisionForgeServerDatabaseJob::class, 1);
    Queue::assertNotPushed(CreateForgeServerDatabaseUserJob::class);
    Queue::assertNotPushed(CreateSiteOnForgeJob::class);

    $first = CustomerSubscriptionDeploymentJob::query()
        ->where('batch_id', $batchId)
        ->where('position', 0)
        ->firstOrFail();

    app(DeploymentStepDispatcher::class)->completeAndDispatchNext($first->id);

    Queue::assertPushed(CreateForgeServerDatabaseUserJob::class, 1);
    Queue::assertNotPushed(CreateSiteOnForgeJob::class);

    $second = CustomerSubscriptionDeploymentJob::query()
        ->where('batch_id', $batchId)
        ->where('position', 1)
        ->firstOrFail();

    app(DeploymentStepDispatcher::class)->completeAndDispatchNext($second->id);

    Queue::assertPushed(CreateSiteOnForgeJob::class, 1);
});

it('queue single template step creates one deployment job row and dispatches that job', function () {
    Queue::fake();

    $type = SubscriptionType::factory()->create(['project_type' => 'static']);
    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => $customer->id,
        'server_id' => 1,
    ]);

    $scheduler = app(SiteDeploymentScheduler::class);
    $template = $scheduler->getCompleteCreationPipelineTemplate($subscription);
    expect($template)->toBeArray()->not->toBeEmpty();

    $batchId = $scheduler->queueSingleTemplateStep($subscription, 0);
    expect($batchId)->not->toBeEmpty();
    expect(CustomerSubscriptionDeploymentJob::query()->where('batch_id', $batchId)->count())->toBe(1);

    Queue::assertPushed(CreateSiteOnForgeJob::class, 1);
});

it('retry deployment job resets a failed row in place and dispatches it', function () {
    Queue::fake();

    $type = SubscriptionType::factory()->create(['project_type' => 'static']);
    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => $customer->id,
        'server_id' => 1,
        'last_deployment_error' => 'Unauthenticated.',
        'last_deployment_error_at' => now(),
    ]);

    $batchId = (string) Str::uuid();
    $failed = CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $subscription->id,
        'batch_id' => $batchId,
        'position' => 0,
        'job_name' => 'create_site',
        'status' => CustomerSubscriptionDeploymentJob::STATUS_FAILED,
        'error_message' => '{"message":"Unauthenticated."}',
        'started_at' => now()->subMinutes(2),
        'finished_at' => now()->subMinute(),
    ]);
    CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $subscription->id,
        'batch_id' => $batchId,
        'position' => 1,
        'job_name' => 'ensure_forge_site',
        'status' => CustomerSubscriptionDeploymentJob::STATUS_PENDING,
    ]);

    $returned = app(SiteDeploymentScheduler::class)->retryDeploymentJob($failed);

    expect($returned)->toBe($batchId);
    $failed->refresh();
    expect($failed->batch_id)->toBe($batchId)
        ->and($failed->position)->toBe(0)
        ->and($failed->status)->toBe(CustomerSubscriptionDeploymentJob::STATUS_RUNNING)
        ->and($failed->error_message)->toBeNull()
        ->and($failed->finished_at)->toBeNull();

    $subscription->refresh();
    expect($subscription->last_deployment_error)->toBeNull()
        ->and($subscription->last_deployment_error_at)->toBeNull();

    Queue::assertPushed(CreateSiteOnForgeJob::class, 1);
});

it('completing a retried deployment job dispatches the next step in the same batch', function () {
    Queue::fake();

    $type = SubscriptionType::factory()->create(['project_type' => 'static']);
    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => $customer->id,
        'server_id' => 1,
    ]);

    $batchId = (string) Str::uuid();
    $failed = CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $subscription->id,
        'batch_id' => $batchId,
        'position' => 0,
        'job_name' => 'create_site',
        'status' => CustomerSubscriptionDeploymentJob::STATUS_FAILED,
        'error_message' => 'boom',
        'started_at' => now()->subMinutes(2),
        'finished_at' => now()->subMinute(),
    ]);
    CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $subscription->id,
        'batch_id' => $batchId,
        'position' => 1,
        'job_name' => 'ensure_forge_site',
        'status' => CustomerSubscriptionDeploymentJob::STATUS_PENDING,
    ]);

    app(SiteDeploymentScheduler::class)->retryDeploymentJob($failed);
    Queue::assertPushed(CreateSiteOnForgeJob::class, 1);

    app(DeploymentStepDispatcher::class)->completeAndDispatchNext($failed->id);

    Queue::assertPushed(EnsureForgeSiteIdJob::class, 1);
});

it('retry deployment job throws when another row in the batch is running', function () {
    Queue::fake();

    $type = SubscriptionType::factory()->create(['project_type' => 'static']);
    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => $customer->id,
        'server_id' => 1,
    ]);

    $batchId = (string) Str::uuid();
    $failed = CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $subscription->id,
        'batch_id' => $batchId,
        'position' => 0,
        'job_name' => 'create_site',
        'status' => CustomerSubscriptionDeploymentJob::STATUS_FAILED,
        'error_message' => 'boom',
    ]);
    CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $subscription->id,
        'batch_id' => $batchId,
        'position' => 1,
        'job_name' => 'ensure_forge_site',
        'status' => CustomerSubscriptionDeploymentJob::STATUS_RUNNING,
        'started_at' => now(),
    ]);

    expect(fn () => app(SiteDeploymentScheduler::class)->retryDeploymentJob($failed))
        ->toThrow(RuntimeException::class);

    Queue::assertNothingPushed();
});

it('requeue deployment job alone creates a new batch copying job name and parameters', function () {
    Queue::fake();

    $type = SubscriptionType::factory()->create(['project_type' => 'static']);
    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => $customer->id,
        'server_id' => 1,
    ]);

    $source = CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $subscription->id,
        'batch_id' => (string) Str::uuid(),
        'position' => 3,
        'job_name' => 'send_forge_command',
        'parameters' => ['command' => 'php artisan migrate --force'],
        'status' => CustomerSubscriptionDeploymentJob::STATUS_FAILED,
        'error_message' => 'boom',
    ]);

    $newBatchId = app(SiteDeploymentScheduler::class)->requeueDeploymentJobAlone($source);

    expect($newBatchId)->not->toBe($source->batch_id);

    $copy = CustomerSubscriptionDeploymentJob::query()
        ->where('batch_id', $newBatchId)
        ->firstOrFail();

    expect($copy->position)->toBe(0)
        ->and($copy->job_name)->toBe('send_forge_command')
        ->and($copy->parameters)->toBe(['command' => 'php artisan migrate --force'])
        ->and($copy->status)->toBe(CustomerSubscriptionDeploymentJob::STATUS_RUNNING)
        ->and(CustomerSubscriptionDeploymentJob::query()->where('batch_id', $source->batch_id)->count())->toBe(1);

    Queue::assertPushed(SendCommandToForgeJob::class, 1);
});
