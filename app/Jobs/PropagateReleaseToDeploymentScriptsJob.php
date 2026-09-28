<?php

namespace App\Jobs;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-renders and pushes the Forge deployment script for every non-pinned, provisioned site of a
 * subscription type after its current release changes, so the next deploy checks out the new tag.
 * Does not trigger a deploy; sites stay "outdated" until an upgrade is run.
 */
class PropagateReleaseToDeploymentScriptsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Spacing between per-site Forge script pushes to stay clear of Forge API rate limits.
     */
    public const STAGGER_SECONDS = 10;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @param  int|null  $releaseId  Release that was promoted; the run is skipped when the type has since moved on. Null pushes whatever each site currently targets.
     */
    public function __construct(
        public int $subscriptionTypeId,
        public ?int $releaseId = null
    ) {}

    public function handle(): void
    {
        $type = SubscriptionType::query()->find($this->subscriptionTypeId);

        if (! $type) {
            Log::warning('release_propagation.missing_type', [
                'subscription_type_id' => $this->subscriptionTypeId,
            ]);

            return;
        }

        if ($this->releaseId !== null && (int) $type->current_release_id !== $this->releaseId) {
            Log::info('release_propagation.superseded', [
                'subscription_type_id' => $type->id,
                'release_id' => $this->releaseId,
                'current_release_id' => $type->current_release_id,
            ]);

            return;
        }

        $subscriptions = CustomerSubscription::query()
            ->where('subscription_type_id', $type->id)
            ->whereNull('pinned_release_id')
            ->whereNotNull('server_id')
            ->whereNotNull('forge_site_id')
            ->orderBy('server_id')
            ->orderBy('id')
            ->get();

        $startAt = now();

        foreach ($subscriptions->values() as $index => $subscription) {
            SendDeploymentScriptJob::dispatch($subscription)
                ->delay($startAt->copy()->addSeconds($index * self::STAGGER_SECONDS));
        }

        Log::info('release_propagation.completed', [
            'subscription_type_id' => $type->id,
            'release_id' => $this->releaseId,
            'queued' => $subscriptions->count(),
        ]);
    }
}
