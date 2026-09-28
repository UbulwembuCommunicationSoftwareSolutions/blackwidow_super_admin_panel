<?php

use App\Jobs\PropagateReleaseToDeploymentScriptsJob;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeRelease;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake([PropagateReleaseToDeploymentScriptsJob::class]);
    Cache::flush();
    config(['services.github.token' => 'test-token']);
});

/**
 * @param  list<array<string, mixed>>  $releases
 * @param  array<string, string>  $tagShas
 */
function fakeGithubReleases(string $repo, array $releases, array $tagShas = []): void
{
    $fakes = [
        "api.github.com/repos/{$repo}/releases*" => Http::response($releases),
    ];
    foreach ($tagShas as $tag => $sha) {
        $fakes["api.github.com/repos/{$repo}/git/ref/tags/{$tag}"] = Http::response([
            'object' => ['sha' => $sha, 'type' => 'commit'],
        ]);
    }
    Http::fake($fakes);
}

/**
 * @return array<string, mixed>
 */
function githubRelease(int $id, string $tag, string $publishedAt, bool $prerelease = false): array
{
    return [
        'id' => $id,
        'tag_name' => $tag,
        'name' => $tag,
        'body' => "Notes for {$tag}",
        'draft' => false,
        'prerelease' => $prerelease,
        'published_at' => $publishedAt,
    ];
}

it('rejects release endpoints without a token', function (string $method, string $uri) {
    $type = SubscriptionType::factory()->create();
    $release = SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id]);

    $uri = str_replace(['{id}', '{release}'], [$type->id, $release->id], $uri);

    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/backend/subscription-types/{id}/releases'],
    'sync' => ['POST', '/api/backend/subscription-types/{id}/releases/sync'],
    'promote' => ['POST', '/api/backend/subscription-types/{id}/releases/{release}/promote'],
]);

it('forbids release endpoints without Shield permissions', function (string $method, string $uri) {
    actingAsBackendForbidden();
    $type = SubscriptionType::factory()->create();
    $release = SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id]);

    $uri = str_replace(['{id}', '{release}'], [$type->id, $release->id], $uri);

    $this->json($method, $uri)->assertForbidden();
})->with([
    'index' => ['GET', '/api/backend/subscription-types/{id}/releases'],
    'sync' => ['POST', '/api/backend/subscription-types/{id}/releases/sync'],
    'promote' => ['POST', '/api/backend/subscription-types/{id}/releases/{release}/promote'],
]);

it('forbids sync and promote for users who can only view subscription types', function () {
    actingAsBackendUser(['ViewAny:SubscriptionType', 'View:SubscriptionType']);
    $type = SubscriptionType::factory()->create();
    $release = SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id]);

    $this->getJson("/api/backend/subscription-types/{$type->id}/releases")->assertSuccessful();
    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/sync")->assertForbidden();
    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/{$release->id}/promote")->assertForbidden();
});

it('returns 404 for releases of a missing subscription type', function () {
    actingAsBackendUser();

    $this->getJson('/api/backend/subscription-types/999999/releases')->assertNotFound();
});

it('lists published releases newest first with site counts and the current release id', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create();
    $old = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v1.0.0',
        'published_at' => Carbon::parse('2026-01-01'),
    ]);
    $new = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v1.1.0',
        'published_at' => Carbon::parse('2026-02-01'),
    ]);
    SubscriptionTypeRelease::factory()->draft()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v0.9.0',
    ]);
    SubscriptionTypeRelease::factory()->create(['tag' => 'v9.9.9']);
    $type->forceFill(['current_release_id' => $old->id])->save();
    CustomerSubscription::factory()->count(2)->create([
        'subscription_type_id' => $type->id,
        'deployed_release_id' => $old->id,
    ]);

    $response = $this->getJson("/api/backend/subscription-types/{$type->id}/releases")
        ->assertSuccessful()
        ->assertJsonPath('current_release_id', $old->id)
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.1.id', $old->id)
        ->assertJsonPath('data.1.sites_on_release', 2)
        ->assertJsonPath('data.0.sites_on_release', 0);

    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('last_synced_at'))->not->toBeNull();
});

it('includes drafts when asked and filters prereleases when stable is requested', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create();
    SubscriptionTypeRelease::factory()->create(['subscription_type_id' => $type->id, 'tag' => 'v1.0.0']);
    SubscriptionTypeRelease::factory()->prerelease()->create(['subscription_type_id' => $type->id, 'tag' => 'v1.1.0']);
    SubscriptionTypeRelease::factory()->draft()->create(['subscription_type_id' => $type->id, 'tag' => 'v0.1.0']);

    $withDrafts = $this->getJson("/api/backend/subscription-types/{$type->id}/releases?include_drafts=1")
        ->assertSuccessful();
    expect($withDrafts->json('data'))->toHaveCount(3);

    $stable = $this->getJson("/api/backend/subscription-types/{$type->id}/releases?stable=1")
        ->assertSuccessful();
    expect(collect($stable->json('data'))->pluck('tag')->all())->toBe(['v1.0.0']);
});

it('validates release list filters', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create();

    $this->getJson("/api/backend/subscription-types/{$type->id}/releases?per_page=500&stable=maybe")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['per_page', 'stable']);
});

it('syncs releases from github and returns the refreshed type', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create(['github_repo' => 'acme/console']);

    fakeGithubReleases('acme/console', [
        githubRelease(2, 'v1.1.0', '2026-02-01T00:00:00Z'),
        githubRelease(1, 'v1.0.0', '2026-01-01T00:00:00Z'),
    ], [
        'v1.1.0' => str_repeat('b', 40),
        'v1.0.0' => str_repeat('a', 40),
    ]);

    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/sync")
        ->assertSuccessful()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('type.id', $type->id)
        ->assertJsonPath('type.latest_release.tag', 'v1.1.0')
        ->assertJsonPath('type.current_release', null)
        ->assertJsonPath('data.0.tag', 'v1.1.0')
        ->assertJsonPath('data.0.commit_sha', str_repeat('b', 40));

    expect($type->releases()->count())->toBe(2);
    expect($type->fresh()->current_release_id)->toBeNull();
});

it('auto promotes the newest stable release on sync when enabled', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create([
        'github_repo' => 'acme/console',
        'auto_promote_stable' => true,
    ]);

    fakeGithubReleases('acme/console', [
        githubRelease(3, 'v1.2.0-rc.1', '2026-03-01T00:00:00Z', prerelease: true),
        githubRelease(2, 'v1.1.0', '2026-02-01T00:00:00Z'),
    ], [
        'v1.2.0-rc.1' => str_repeat('c', 40),
        'v1.1.0' => str_repeat('b', 40),
    ]);

    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/sync")
        ->assertSuccessful()
        ->assertJsonPath('type.current_release.tag', 'v1.1.0')
        ->assertJsonPath('type.master_version', 'v1.1.0')
        ->assertJsonPath('type.latest_release.tag', 'v1.2.0-rc.1');
});

it('reuses stored commit shas instead of resolving known tags again', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create(['github_repo' => 'acme/console']);
    SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v1.0.0',
        'commit_sha' => str_repeat('a', 40),
    ]);

    fakeGithubReleases('acme/console', [
        githubRelease(2, 'v1.1.0', '2026-02-01T00:00:00Z'),
        githubRelease(1, 'v1.0.0', '2026-01-01T00:00:00Z'),
    ], [
        'v1.1.0' => str_repeat('b', 40),
    ]);

    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/sync")->assertSuccessful();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'git/ref/tags/v1.0.0'));
    expect($type->releases()->where('tag', 'v1.0.0')->value('commit_sha'))->toBe(str_repeat('a', 40));
});

it('refetches releases when github answers 304 but the cached body is gone', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create(['github_repo' => 'acme/console']);
    Cache::put('github.releases.etag.acme/console.1', '"stale"', now()->addDay());

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/releases')) {
            return $request->hasHeader('If-None-Match')
                ? Http::response(null, 304)
                : Http::response([githubRelease(1, 'v1.0.0', '2026-01-01T00:00:00Z')]);
        }

        return Http::response(['object' => ['sha' => str_repeat('a', 40), 'type' => 'commit']]);
    });

    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/sync")->assertSuccessful();

    expect($type->releases()->pluck('tag')->all())->toBe(['v1.0.0']);
});

it('returns a clear 422 when the github token is not configured', function () {
    actingAsBackendUser();
    config(['services.github.token' => null]);
    Http::fake();
    $type = SubscriptionType::factory()->create(['github_repo' => 'acme/console']);

    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/sync")
        ->assertStatus(422)
        ->assertJsonPath('message', 'GitHub is not configured: set GITHUB_TOKEN in your .env.');
});

it('returns a clear 422 when github cannot find the repository', function () {
    actingAsBackendUser();
    Http::fake([
        'api.github.com/*' => Http::response(['message' => 'Not Found'], 404),
    ]);
    $type = SubscriptionType::factory()->create(['github_repo' => 'acme/missing']);

    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/sync")
        ->assertStatus(422)
        ->assertJsonPath('message', 'GitHub repository acme/missing was not found, or the token cannot see it (404).');
});

it('returns a clear 422 when github rejects the token', function () {
    actingAsBackendUser();
    Http::fake([
        'api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401),
    ]);
    $type = SubscriptionType::factory()->create(['github_repo' => 'acme/console']);

    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/sync")
        ->assertStatus(422)
        ->assertJsonPath('message', 'GitHub rejected the token (401). Check GITHUB_TOKEN on the API server.');
});

it('returns 422 when syncing a type with no github repository', function () {
    actingAsBackendUser();
    Http::fake();
    $type = SubscriptionType::factory()->create(['github_repo' => '']);

    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/sync")
        ->assertStatus(422)
        ->assertJsonPath('message', 'This subscription type has no GitHub repository configured.');

    Http::assertNothingSent();
});

it('returns 422 when the stored github repository is not valid', function () {
    actingAsBackendUser();
    Http::fake();
    $type = SubscriptionType::factory()->create(['github_repo' => 'not a repo']);

    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/sync")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invalid GitHub repository: not a repo');
});

it('promotes a release to current and mirrors the tag into master_version', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create(['master_version' => 'v0.1']);
    $release = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v2.0.0',
    ]);

    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/{$release->id}/promote")
        ->assertSuccessful()
        ->assertJsonPath('data.current_release_id', $release->id)
        ->assertJsonPath('data.master_version', 'v2.0.0')
        ->assertJsonPath('data.current_release.tag', 'v2.0.0');

    Queue::assertPushed(PropagateReleaseToDeploymentScriptsJob::class);
});

it('refuses to promote a release that is gone from github', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create();
    $release = SubscriptionTypeRelease::factory()->draft()->create(['subscription_type_id' => $type->id]);

    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/{$release->id}/promote")
        ->assertStatus(422);

    expect($type->fresh()->current_release_id)->toBeNull();
});

it('returns 404 when promoting a release of another subscription type', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create();
    $foreign = SubscriptionTypeRelease::factory()->create();

    $this->postJson("/api/backend/subscription-types/{$type->id}/releases/{$foreign->id}/promote")
        ->assertNotFound();
});

it('exposes the current and latest release on the subscription type list', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create();
    $current = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v1.0.0',
        'published_at' => Carbon::parse('2026-01-01'),
    ]);
    SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v1.1.0',
        'published_at' => Carbon::parse('2026-02-01'),
    ]);
    SubscriptionTypeRelease::factory()->draft()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v9.0.0',
    ]);
    $type->forceFill(['current_release_id' => $current->id])->save();

    $row = collect($this->getJson('/api/backend/subscription-types?per_page=100')
        ->assertSuccessful()
        ->json('data'))->firstWhere('id', $type->id);

    expect($row['current_release']['tag'])->toBe('v1.0.0');
    expect($row['latest_release']['tag'])->toBe('v1.1.0');
});
