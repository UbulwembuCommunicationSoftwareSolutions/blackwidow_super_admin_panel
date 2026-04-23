<?php

use App\Jobs\SiteDeployment\CreateSiteOnForgeJob;
use App\Jobs\SiteDeployment\EnsureForgeSiteIdJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionDeploymentJob;
use App\Models\SubscriptionType;
use App\Services\DeploymentStepDispatcher;
use App\Services\SiteDeploymentScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('persists deployment steps and dispatches only create site first', function () {
    Queue::fake();

    $type = SubscriptionType::factory()->create();
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
    Queue::assertNotPushed(EnsureForgeSiteIdJob::class);
});

it('dispatches ensure forge site after create site completes', function () {
    Queue::fake();

    $type = SubscriptionType::factory()->create();
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
