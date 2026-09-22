<?php

namespace App\Jobs;

use App\Models\SubscriptionType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAllGithubReleasesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function handle(): void
    {
        $types = SubscriptionType::query()
            ->whereNotNull('github_repo')
            ->where('github_repo', '!=', '')
            ->get(['id', 'github_repo']);

        foreach ($types as $type) {
            SyncGithubReleasesJob::dispatch((int) $type->id);
        }

        Log::info('github_releases.sync_all.dispatched', [
            'count' => $types->count(),
        ]);
    }
}
