<?php

use App\Jobs\SiteDeployment\AddDeploymentScriptOnForgeJob;
use App\Jobs\SiteDeployment\DeploySite;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionDeploymentJob;
use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeRelease;
use App\Services\SiteDeploymentJobName;
use App\Services\SiteDeploymentScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('scheduleUpgrade queues add deployment script then deploy site', function () {
    Queue::fake();

    $type = SubscriptionType::factory()->create(['project_type' => 'static']);
    $release = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v1.0.0',
    ]);
    $type->forceFill(['current_release_id' => $release->id])->save();

    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'site_deployment_queue_started_at' => null,
        'server_id' => 1,
        'forge_site_id' => 2,
    ]);

    $batchId = app(SiteDeploymentScheduler::class)->scheduleUpgrade($subscription, true);

    $rows = CustomerSubscriptionDeploymentJob::query()
        ->where('batch_id', $batchId)
        ->orderBy('position')
        ->get();

    expect($rows)->toHaveCount(2);
    expect($rows[0]->job_name)->toBe(SiteDeploymentJobName::ADD_DEPLOYMENT_SCRIPT);
    expect($rows[1]->job_name)->toBe(SiteDeploymentJobName::DEPLOY_SITE);
    expect($rows[0]->parameters['release_id'] ?? null)->toBe($release->id);

    Queue::assertPushed(AddDeploymentScriptOnForgeJob::class, 1);
    Queue::assertNotPushed(DeploySite::class);
});
