<?php

use App\Jobs\PropagateReleaseToDeploymentScriptsJob;
use App\Jobs\SendDeploymentScriptJob;
use App\Jobs\SyncGithubReleasesJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\DeploymentTemplate;
use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeRelease;
use App\Services\DeploymentScriptRenderer;
use App\Services\GithubReleaseClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function provisionedSubscription(SubscriptionType $type, array $attributes = []): CustomerSubscription
{
    return CustomerSubscription::factory()->create(array_merge([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'server_id' => 11,
        'forge_site_id' => '22',
    ], $attributes));
}

it('propagates the promoted release to deployment scripts of unpinned provisioned sites only', function () {
    Queue::fake();
    actingAsBackendUser();

    $type = SubscriptionType::factory()->create(['current_release_id' => null]);
    $previous = SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id, 'tag' => 'v1.41']);
    $release = SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id, 'tag' => 'v1.42']);

    $unpinned = provisionedSubscription($type);
    provisionedSubscription($type, ['pinned_release_id' => $previous->id]);
    provisionedSubscription($type, ['server_id' => null, 'forge_site_id' => null]);

    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/{$release->id}/promote")
        ->assertSuccessful();

    Queue::assertPushed(
        PropagateReleaseToDeploymentScriptsJob::class,
        fn (PropagateReleaseToDeploymentScriptsJob $job): bool => $job->subscriptionTypeId === $type->id
            && $job->releaseId === $release->id
    );

    (new PropagateReleaseToDeploymentScriptsJob($type->id, $release->id))->handle();

    Queue::assertPushed(SendDeploymentScriptJob::class, 1);
    Queue::assertPushed(
        SendDeploymentScriptJob::class,
        fn (SendDeploymentScriptJob $job): bool => $job->customerSubscription->is($unpinned)
    );
});

it('does not propagate when a subscription type is saved without changing the current release', function () {
    $type = SubscriptionType::factory()->create();
    $release = SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id]);
    $type->forceFill(['current_release_id' => $release->id])->save();

    Queue::fake();

    $type->update(['name' => 'Renamed console']);

    Queue::assertNotPushed(PropagateReleaseToDeploymentScriptsJob::class);
});

it('does not propagate when the current release is cleared', function () {
    $type = SubscriptionType::factory()->create();
    $release = SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id]);
    $type->forceFill(['current_release_id' => $release->id])->save();

    Queue::fake();

    $type->forceFill(['current_release_id' => null])->save();

    Queue::assertNotPushed(PropagateReleaseToDeploymentScriptsJob::class);
});

it('skips a propagation run that was superseded by a newer promotion', function () {
    $type = SubscriptionType::factory()->create();
    $older = SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id, 'tag' => 'v1.41']);
    $newer = SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id, 'tag' => 'v1.42']);
    provisionedSubscription($type);

    Queue::fake();
    $type->forceFill(['current_release_id' => $newer->id])->save();

    (new PropagateReleaseToDeploymentScriptsJob($type->id, $older->id))->handle();

    Queue::assertNotPushed(SendDeploymentScriptJob::class);
});

it('staggers deployment script pushes across sites', function () {
    $type = SubscriptionType::factory()->create();
    $release = SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id]);
    provisionedSubscription($type);
    provisionedSubscription($type);

    Queue::fake();
    $type->forceFill(['current_release_id' => $release->id])->save();

    $this->freezeTime();
    (new PropagateReleaseToDeploymentScriptsJob($type->id, $release->id))->handle();

    $delays = collect(Queue::pushed(SendDeploymentScriptJob::class))
        ->map(fn (SendDeploymentScriptJob $job): int => (int) now()->diffInSeconds($job->delay))
        ->sort()
        ->values()
        ->all();

    expect($delays)->toBe([0, PropagateReleaseToDeploymentScriptsJob::STAGGER_SECONDS]);
});

it('propagates when a stable github release is auto-promoted on sync', function () {
    config(['services.github.token' => 'test-token']);
    Queue::fake();

    $type = SubscriptionType::factory()->create([
        'github_repo' => 'acme/console',
        'auto_promote_stable' => true,
        'current_release_id' => null,
    ]);

    Http::fake([
        'api.github.com/repos/acme/console/releases*' => Http::response([
            [
                'id' => 142,
                'tag_name' => 'v1.42',
                'name' => 'v1.42',
                'body' => 'Changelog',
                'draft' => false,
                'prerelease' => false,
                'published_at' => '2026-09-28T12:41:35Z',
            ],
        ]),
        'api.github.com/repos/acme/console/git/ref/tags/v1.42' => Http::response([
            'object' => ['sha' => str_repeat('a', 40), 'type' => 'commit'],
        ]),
    ]);

    (new SyncGithubReleasesJob($type->id))->handle(app(GithubReleaseClient::class));

    $release = SubscriptionTypeRelease::query()->where('subscription_type_id', $type->id)->where('tag', 'v1.42')->firstOrFail();

    expect($type->fresh()->current_release_id)->toBe($release->id);
    Queue::assertPushed(
        PropagateReleaseToDeploymentScriptsJob::class,
        fn (PropagateReleaseToDeploymentScriptsJob $job): bool => $job->releaseId === $release->id
    );
});

it('re-renders the deployment script of a site when it is pinned or unpinned', function () {
    $type = SubscriptionType::factory()->create();
    $current = SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id, 'tag' => 'v1.42']);
    $older = SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id, 'tag' => 'v1.41']);
    $type->forceFill(['current_release_id' => $current->id])->save();
    $subscription = provisionedSubscription($type);
    $other = provisionedSubscription($type);

    Queue::fake();
    actingAsBackendUser();

    $this->patchJson("/api/backend/customer-subscriptions/{$subscription->id}", ['pinned_release_id' => $older->id])
        ->assertSuccessful();

    Queue::assertPushed(SendDeploymentScriptJob::class, 1);
    Queue::assertPushed(
        SendDeploymentScriptJob::class,
        fn (SendDeploymentScriptJob $job): bool => $job->customerSubscription->is($subscription)
    );

    $this->patchJson("/api/backend/customer-subscriptions/{$subscription->id}", ['pinned_release_id' => null])
        ->assertSuccessful();

    Queue::assertPushed(SendDeploymentScriptJob::class, 2);
    Queue::assertNotPushed(
        SendDeploymentScriptJob::class,
        fn (SendDeploymentScriptJob $job): bool => $job->customerSubscription->is($other)
    );
});

it('does not push a deployment script when pinning an unprovisioned site or updating other fields', function () {
    $type = SubscriptionType::factory()->create();
    $release = SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id]);
    $type->forceFill(['current_release_id' => $release->id])->save();
    $unprovisioned = provisionedSubscription($type, ['server_id' => null, 'forge_site_id' => null]);
    $provisioned = provisionedSubscription($type);

    Queue::fake();
    actingAsBackendUser();

    $this->patchJson("/api/backend/customer-subscriptions/{$unprovisioned->id}", ['pinned_release_id' => $release->id])
        ->assertSuccessful();
    $this->patchJson("/api/backend/customer-subscriptions/{$provisioned->id}", ['app_name' => 'Renamed'])
        ->assertSuccessful();

    Queue::assertNotPushed(SendDeploymentScriptJob::class);
});

it('renders the promoted tag into the checkout line of the propagated deployment script', function () {
    Queue::fake();
    actingAsBackendUser();

    $type = SubscriptionType::factory()->create(['current_release_id' => null]);
    $release = SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id, 'tag' => 'v1.42']);
    DeploymentTemplate::query()->create([
        'subscription_type_id' => $type->id,
        'script' => "cd \$FORGE_SITE_PATH\ngit fetch --tags --force origin\ngit checkout --force #RELEASE_TAG#\n",
    ]);
    $subscription = provisionedSubscription($type, ['domain' => 'demo.console.test']);

    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/{$release->id}/promote")
        ->assertSuccessful();

    (new PropagateReleaseToDeploymentScriptsJob($type->id, $release->id))->handle();

    $pushed = collect(Queue::pushed(SendDeploymentScriptJob::class))->sole();

    [$script, $changed] = app(DeploymentScriptRenderer::class)->render($pushed->customerSubscription->fresh());

    expect($changed)->toBeTrue();
    expect($script->customer_subscription_id)->toBe($subscription->id);
    expect($script->rendered_release_id)->toBe($release->id);
    expect($script->script)
        ->toContain('git fetch --tags --force origin')
        ->toContain('git checkout --force v1.42')
        ->not->toContain('#RELEASE_TAG#');
});
