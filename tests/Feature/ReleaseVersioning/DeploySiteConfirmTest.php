<?php

use App\Helpers\ForgeApi;
use App\Jobs\SiteDeployment\DeploySite;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionDeploymentJob;
use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeRelease;
use App\Services\SiteDeploymentJobName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Forge\Resources\Deployment;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.forge.key' => 'test-key', 'services.forge.organization' => 'test-org']);
});

it('parses the BW_DEPLOYED_RELEASE marker from a forge log', function () {
    $api = new ForgeApi;

    expect($api->parseDeployedReleaseMarker(
        "Installing...\nBW_DEPLOYED_RELEASE v1.4.2 abcdefabcdefabcdefabcdefabcdefabcdefabcd\nDone\n"
    ))->toBe([
        'tag' => 'v1.4.2',
        'commit_sha' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcd',
    ]);

    expect($api->parseDeployedReleaseMarker('no marker here'))->toBeNull();
});

it('confirms deployed release after a finished forge deploy', function () {
    $type = SubscriptionType::factory()->create();
    $release = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v9.9.9',
        'commit_sha' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcd',
    ]);
    $type->forceFill(['current_release_id' => $release->id])->save();

    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'server_id' => 10,
        'forge_site_id' => 20,
        'deployed_release_id' => null,
        'deployed_version' => 'old',
    ]);

    $jobRow = CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $sub->id,
        'batch_id' => (string) Str::uuid(),
        'position' => 0,
        'job_name' => SiteDeploymentJobName::DEPLOY_SITE,
        'status' => CustomerSubscriptionDeploymentJob::STATUS_RUNNING,
    ]);

    $deployment = new Deployment([
        'id' => 55,
        'status' => 'finished',
        'commit' => ['hash' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcd'],
    ]);

    $forge = Mockery::mock(ForgeApi::class)->makePartial();
    $forge->shouldReceive('assertForgeSiteReady')->andReturnUsing(fn ($s) => $s);
    $forge->shouldReceive('deploySite')->once()->andReturn($deployment);
    $forge->shouldReceive('waitForDeployment')->once()->andReturn($deployment);
    $forge->shouldReceive('deploymentLog')->once()->andReturn(
        "BW_DEPLOYED_RELEASE v9.9.9 abcdefabcdefabcdefabcdefabcdefabcdefabcd\n"
    );
    app()->instance(ForgeApi::class, $forge);

    (new DeploySite($sub->id, $jobRow->id))->handle();

    $sub->refresh();
    expect($sub->deployed_release_id)->toBe($release->id);
    expect($sub->deployed_commit_sha)->toBe('abcdefabcdefabcdefabcdefabcdefabcdefabcd');
    expect($sub->deployed_tag_raw)->toBe('v9.9.9');
    expect($sub->deployed_version)->toBe('v9.9.9');
    expect($sub->deployed_confirmed_at)->not->toBeNull();
});

it('does not overwrite deployed columns when forge reports failure', function () {
    $type = SubscriptionType::factory()->create();
    $previous = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v1.0.0',
        'commit_sha' => str_repeat('1', 40),
    ]);
    $target = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v2.0.0',
        'commit_sha' => str_repeat('2', 40),
    ]);
    $type->forceFill(['current_release_id' => $target->id])->save();

    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'server_id' => 10,
        'forge_site_id' => 20,
        'deployed_release_id' => $previous->id,
        'deployed_version' => 'v1.0.0',
        'deployed_commit_sha' => str_repeat('1', 40),
    ]);

    $jobRow = CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $sub->id,
        'batch_id' => (string) Str::uuid(),
        'position' => 0,
        'job_name' => SiteDeploymentJobName::DEPLOY_SITE,
        'status' => CustomerSubscriptionDeploymentJob::STATUS_RUNNING,
    ]);

    $deployment = new Deployment(['id' => 56, 'status' => 'failed', 'commit' => null]);

    $forge = Mockery::mock(ForgeApi::class)->makePartial();
    $forge->shouldReceive('assertForgeSiteReady')->andReturnUsing(fn ($s) => $s);
    $forge->shouldReceive('deploySite')->once()->andReturn($deployment);
    $forge->shouldReceive('waitForDeployment')->once()->andReturn($deployment);
    $forge->shouldReceive('deploymentLog')->once()->andReturn('error');
    app()->instance(ForgeApi::class, $forge);

    (new DeploySite($sub->id, $jobRow->id))->handle();

    $sub->refresh();
    expect($sub->deployed_release_id)->toBe($previous->id);
    expect($sub->deployed_version)->toBe('v1.0.0');
    expect($jobRow->fresh()->status)->toBe(CustomerSubscriptionDeploymentJob::STATUS_FAILED);
});

it('is idempotent when DEPLOY_SITE runs twice for the same confirmed release', function () {
    $type = SubscriptionType::factory()->create();
    $release = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v3.0.0',
        'commit_sha' => str_repeat('a', 40),
    ]);
    $type->forceFill(['current_release_id' => $release->id])->save();

    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'server_id' => 10,
        'forge_site_id' => 20,
        'deployed_release_id' => $release->id,
        'deployed_commit_sha' => str_repeat('a', 40),
        'deployed_confirmed_at' => now()->subMinute(),
        'deployed_version' => 'v3.0.0',
        'deployed_at' => now()->subMinute(),
    ]);

    $confirmedAt = $sub->deployed_confirmed_at->copy();

    $jobRow = CustomerSubscriptionDeploymentJob::query()->create([
        'customer_subscription_id' => $sub->id,
        'batch_id' => (string) Str::uuid(),
        'position' => 1,
        'job_name' => SiteDeploymentJobName::DEPLOY_SITE,
        'status' => CustomerSubscriptionDeploymentJob::STATUS_RUNNING,
    ]);

    $deployment = new Deployment([
        'id' => 57,
        'status' => 'finished',
        'commit' => ['hash' => str_repeat('a', 40)],
    ]);

    $forge = Mockery::mock(ForgeApi::class)->makePartial();
    $forge->shouldReceive('assertForgeSiteReady')->andReturnUsing(fn ($s) => $s);
    $forge->shouldReceive('deploySite')->once()->andReturn($deployment);
    $forge->shouldReceive('waitForDeployment')->once()->andReturn($deployment);
    $forge->shouldReceive('deploymentLog')->once()->andReturn(
        'BW_DEPLOYED_RELEASE v3.0.0 '.str_repeat('a', 40)."\n"
    );
    app()->instance(ForgeApi::class, $forge);

    (new DeploySite($sub->id, $jobRow->id))->handle();

    $sub->refresh();
    expect($sub->deployed_release_id)->toBe($release->id);
    expect($sub->deployed_confirmed_at->equalTo($confirmedAt))->toBeTrue();
});
