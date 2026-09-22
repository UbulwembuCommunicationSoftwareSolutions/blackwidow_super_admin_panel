<?php

namespace App\Jobs;

use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeRelease;
use App\Services\GithubReleaseClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGithubReleasesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public int $timeout = 300;

    public function __construct(
        public int $subscriptionTypeId,
        public bool $includeDrafts = false
    ) {}

    public function handle(GithubReleaseClient $client): void
    {
        $type = SubscriptionType::query()->find($this->subscriptionTypeId);
        if (! $type) {
            Log::warning('github_releases.sync.missing_type', [
                'subscription_type_id' => $this->subscriptionTypeId,
            ]);

            return;
        }

        if (blank($type->github_repo)) {
            Log::info('github_releases.sync.skipped_no_repo', [
                'subscription_type_id' => $type->id,
            ]);

            return;
        }

        try {
            $releases = $client->listReleases((string) $type->github_repo, $this->includeDrafts);
        } catch (Throwable $e) {
            Log::error('github_releases.sync.failed', [
                'subscription_type_id' => $type->id,
                'github_repo' => $type->github_repo,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $seenTags = [];
        $synced = 0;

        foreach ($releases as $release) {
            $seenTags[] = $release['tag_name'];

            SubscriptionTypeRelease::query()->updateOrCreate(
                [
                    'subscription_type_id' => $type->id,
                    'tag' => $release['tag_name'],
                ],
                [
                    'commit_sha' => $release['commit_sha'],
                    'name' => $release['name'],
                    'body' => $release['body'],
                    'is_prerelease' => $release['prerelease'],
                    'is_draft' => $release['draft'],
                    'github_release_id' => $release['id'] ?: null,
                    'published_at' => $release['published_at']
                        ? Carbon::parse($release['published_at'])
                        : null,
                    'synced_at' => now(),
                ]
            );
            $synced++;
        }

        // Soft-mark releases that disappeared from GitHub so deployed sites keep their FK.
        if ($seenTags !== []) {
            SubscriptionTypeRelease::query()
                ->where('subscription_type_id', $type->id)
                ->whereNotIn('tag', $seenTags)
                ->where('is_draft', false)
                ->update([
                    'is_draft' => true,
                    'synced_at' => now(),
                ]);
        }

        if ($type->auto_promote_stable) {
            $latestStable = SubscriptionTypeRelease::query()
                ->where('subscription_type_id', $type->id)
                ->stable()
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->first();

            if ($latestStable && (int) $type->current_release_id !== (int) $latestStable->id) {
                $type->forceFill(['current_release_id' => $latestStable->id])->save();
            }
        }

        Log::info('github_releases.sync.completed', [
            'subscription_type_id' => $type->id,
            'github_repo' => $type->github_repo,
            'synced' => $synced,
        ]);
    }
}
