<?php

namespace App\Console\Commands;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionTypeRelease;
use App\Services\SiteDeploymentScheduler;
use Illuminate\Console\Command;

/**
 * Operator checklist / helper for the first canary upgrade onto a tagged release.
 *
 * Typical flow:
 * 1. Tag a commit on the tenant repo and publish a GitHub Release (optionally prerelease).
 * 2. php artisan queue:work (or sync) SyncGithubReleasesJob / click Sync in Filament.
 * 3. php artisan app:backfill-release-versioning
 * 4. php artisan app:canary-release-upgrade {subscription-id} --release=vX.Y.Z --dry-run
 * 5. Re-run without --dry-run to pin + queue.
 * 6. Confirm customer_subscriptions.deployed_release_id / deployed_commit_sha match the release.
 * 7. Only then: app:send-all-sites-deployment {type-id} and RedeployOutdatedSitesJob.
 *
 * Also verify `* * * * * php artisan schedule:run` exists on the server so hourly sync is not decorative.
 */
class CanaryReleaseUpgradeCommand extends Command
{
    protected $signature = 'app:canary-release-upgrade
        {customer-subscription-id : Internal/test subscription to canary}
        {--release= : Tag to pin (e.g. v1.0.0-rc.1)}
        {--dry-run : Show what would happen without queueing}';

    protected $description = 'Pin an internal subscription to a release and queue a canary upgrade';

    public function handle(SiteDeploymentScheduler $scheduler): int
    {
        $sub = CustomerSubscription::query()
            ->with(['subscriptionType.currentRelease', 'pinnedRelease', 'deployedRelease'])
            ->findOrFail($this->argument('customer-subscription-id'));

        $tag = $this->option('release');
        if (! is_string($tag) || $tag === '') {
            $this->error('Pass --release=vX.Y.Z');

            return self::FAILURE;
        }

        $release = SubscriptionTypeRelease::query()
            ->where('subscription_type_id', $sub->subscription_type_id)
            ->where('tag', $tag)
            ->first();

        if (! $release) {
            $this->error("Release {$tag} not found for subscription type {$sub->subscription_type_id}. Sync from GitHub first.");

            return self::FAILURE;
        }

        if ($release->is_draft) {
            $this->error("Release {$tag} is marked draft / missing on GitHub.");

            return self::FAILURE;
        }

        $this->table(['Field', 'Value'], [
            ['subscription_id', $sub->id],
            ['domain', $sub->domain],
            ['type', $sub->subscriptionType?->name],
            ['target_tag', $release->tag],
            ['target_sha', $release->commit_sha],
            ['current_deployed', $sub->deployedRelease?->tag ?? $sub->deployed_version ?? '—'],
            ['dry_run', $this->option('dry-run') ? 'yes' : 'no'],
        ]);

        if ($this->option('dry-run')) {
            $this->info('Dry run only — no pin or upgrade queued.');

            return self::SUCCESS;
        }

        $sub->forceFill(['pinned_release_id' => $release->id])->save();
        $batchId = $scheduler->scheduleUpgrade($sub, force: true);

        $this->info("Pinned to {$release->tag} and queued upgrade batch {$batchId}.");
        $this->line('After the deploy finishes, check deployed_release_id / deployed_commit_sha / deployed_confirmed_at.');
        $this->line('Confirm schedule:run cron is installed before relying on hourly GitHub sync.');

        return self::SUCCESS;
    }
}
