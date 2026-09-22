<?php

namespace App\Console\Commands;

use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeRelease;
use Illuminate\Console\Command;

/**
 * One-time backfill after syncing GitHub releases:
 * - set current_release_id from matching master_version (else latest stable)
 * - set deployed_release_id from matching deployed_version
 * - ensure deployment_scripts.is_custom defaults to false
 */
class BackfillReleaseVersioningCommand extends Command
{
    protected $signature = 'app:backfill-release-versioning {--dry-run : Report without saving}';

    protected $description = 'Backfill current/deployed release FKs from legacy version strings';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $typesUpdated = 0;
        $subsUpdated = 0;

        foreach (SubscriptionType::query()->cursor() as $type) {
            if ($type->current_release_id) {
                continue;
            }

            $release = null;
            if (filled($type->master_version)) {
                $release = SubscriptionTypeRelease::query()
                    ->where('subscription_type_id', $type->id)
                    ->where(function ($q) use ($type): void {
                        $q->where('tag', $type->master_version)
                            ->orWhere('tag', 'v'.ltrim((string) $type->master_version, 'vV'));
                    })
                    ->first();
            }

            if (! $release) {
                $release = SubscriptionTypeRelease::query()
                    ->where('subscription_type_id', $type->id)
                    ->stable()
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->first();
            }

            if (! $release) {
                $this->warn("Type {$type->id} ({$type->name}): no release to backfill.");

                continue;
            }

            $this->line("Type {$type->id}: current_release_id -> {$release->tag}");
            if (! $dryRun) {
                $type->forceFill([
                    'current_release_id' => $release->id,
                    'master_version' => $release->tag,
                ])->save();
            }
            $typesUpdated++;
        }

        foreach (CustomerSubscription::query()->whereNull('deployed_release_id')->cursor() as $sub) {
            if (! filled($sub->deployed_version)) {
                continue;
            }

            $release = SubscriptionTypeRelease::query()
                ->where('subscription_type_id', $sub->subscription_type_id)
                ->where(function ($q) use ($sub): void {
                    $q->where('tag', $sub->deployed_version)
                        ->orWhere('tag', 'v'.ltrim((string) $sub->deployed_version, 'vV'));
                })
                ->first();

            if (! $release) {
                continue;
            }

            $this->line("Subscription {$sub->id}: deployed_release_id -> {$release->tag}");
            if (! $dryRun) {
                $sub->forceFill([
                    'deployed_release_id' => $release->id,
                    'deployed_tag_raw' => $release->tag,
                    'deployed_version' => $release->tag,
                ])->save();
            }
            $subsUpdated++;
        }

        $scripts = DeploymentScript::query()->whereNull('is_custom')->orWhere('is_custom', false)->count();
        if (! $dryRun) {
            DeploymentScript::query()->whereNull('is_custom')->update(['is_custom' => false]);
        }

        $this->info("Types updated: {$typesUpdated}; subscriptions updated: {$subsUpdated}; scripts checked: {$scripts}");

        return self::SUCCESS;
    }
}
