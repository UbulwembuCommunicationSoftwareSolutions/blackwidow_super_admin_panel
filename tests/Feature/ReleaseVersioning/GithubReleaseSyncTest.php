<?php

use App\Jobs\SyncGithubReleasesJob;
use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeRelease;
use App\Services\GithubReleaseClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('syncs github releases into subscription_type_releases', function () {
    config(['services.github.token' => 'test-token']);

    $type = SubscriptionType::factory()->create([
        'github_repo' => 'acme/console',
    ]);

    Http::fake([
        'api.github.com/repos/acme/console/releases*' => Http::response([
            [
                'id' => 101,
                'tag_name' => 'v1.2.0',
                'name' => 'v1.2.0',
                'body' => 'Changelog',
                'draft' => false,
                'prerelease' => false,
                'published_at' => '2026-01-15T12:00:00Z',
            ],
            [
                'id' => 100,
                'tag_name' => 'v1.2.0-rc.1',
                'name' => 'RC',
                'body' => null,
                'draft' => false,
                'prerelease' => true,
                'published_at' => '2026-01-10T12:00:00Z',
            ],
            [
                'id' => 99,
                'tag_name' => 'v0.9.0',
                'name' => 'Draft',
                'body' => null,
                'draft' => true,
                'prerelease' => false,
                'published_at' => null,
            ],
        ], 200, ['ETag' => '"abc"']),
        'api.github.com/repos/acme/console/git/ref/tags/v1.2.0' => Http::response([
            'object' => ['sha' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'type' => 'commit'],
        ]),
        'api.github.com/repos/acme/console/git/ref/tags/v1.2.0-rc.1' => Http::response([
            'object' => ['sha' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'type' => 'commit'],
        ]),
    ]);

    (new SyncGithubReleasesJob($type->id))->handle(app(GithubReleaseClient::class));

    expect(SubscriptionTypeRelease::query()->where('subscription_type_id', $type->id)->count())->toBe(2);
    expect(SubscriptionTypeRelease::query()->where('tag', 'v1.2.0')->first())
        ->commit_sha->toBe('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')
        ->is_prerelease->toBeFalse()
        ->is_draft->toBeFalse();
    expect(SubscriptionTypeRelease::query()->where('tag', 'v1.2.0-rc.1')->first())
        ->is_prerelease->toBeTrue();
});

it('soft-marks missing releases as draft on resync', function () {
    config(['services.github.token' => 'test-token']);

    $type = SubscriptionType::factory()->create(['github_repo' => 'acme/console']);
    $old = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v0.1.0',
        'is_draft' => false,
        'commit_sha' => str_repeat('c', 40),
    ]);

    Http::fake([
        'api.github.com/repos/acme/console/releases*' => Http::response([
            [
                'id' => 101,
                'tag_name' => 'v1.0.0',
                'name' => 'v1.0.0',
                'body' => null,
                'draft' => false,
                'prerelease' => false,
                'published_at' => '2026-02-01T00:00:00Z',
            ],
        ]),
        'api.github.com/repos/acme/console/git/ref/tags/v1.0.0' => Http::response([
            'object' => ['sha' => str_repeat('d', 40), 'type' => 'commit'],
        ]),
    ]);

    (new SyncGithubReleasesJob($type->id))->handle(app(GithubReleaseClient::class));

    expect($old->fresh()->is_draft)->toBeTrue();
    expect(SubscriptionTypeRelease::query()->where('tag', 'v1.0.0')->exists())->toBeTrue();
});
