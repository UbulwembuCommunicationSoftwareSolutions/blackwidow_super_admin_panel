<?php

namespace App\Jobs;

use App\Models\CustomerSubscription;
use App\Services\SiteDeploymentScheduler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RedeployOutdatedSitesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  int|null  $subscriptionTypeId  Limit to one subscription type when set.
     * @param  int  $perServerConcurrency  Max concurrent upgrade batches started per wave per server.
     * @param  list<int>|null  $onlyIds  Optional explicit subscription IDs (used for delayed waves).
     */
    public function __construct(
        public ?int $subscriptionTypeId = null,
        public int $perServerConcurrency = 3,
        public ?array $onlyIds = null
    ) {}

    public function handle(SiteDeploymentScheduler $scheduler): void
    {
        $query = CustomerSubscription::query()
            ->whereOutdated()
            ->with(['subscriptionType.currentRelease', 'pinnedRelease'])
            ->orderBy('server_id')
            ->orderBy('id');

        if ($this->subscriptionTypeId !== null) {
            $query->where('subscription_type_id', $this->subscriptionTypeId);
        }

        if ($this->onlyIds !== null) {
            $query->whereIn('id', $this->onlyIds);
        }

        $perServer = max(1, $this->perServerConcurrency);
        $waves = [];
        $scheduled = 0;
        $skipped = 0;

        foreach ($query->get() as $subscription) {
            $serverKey = (string) ($subscription->server_id ?? 'none');
            $waves[$serverKey] ??= [];
            $waves[$serverKey][] = $subscription;
        }

        foreach ($waves as $serverKey => $subscriptions) {
            foreach (array_chunk($subscriptions, $perServer) as $waveIndex => $chunk) {
                if ($waveIndex === 0) {
                    foreach ($chunk as $subscription) {
                        try {
                            // force: upgrades are intentional re-runs after the initial provision.
                            $scheduler->scheduleUpgrade($subscription, force: true);
                            $scheduled++;
                        } catch (Throwable $e) {
                            $skipped++;
                            Log::warning('redeploy_outdated.skip', [
                                'customer_subscription_id' => $subscription->id,
                                'message' => $e->getMessage(),
                            ]);
                        }
                    }

                    continue;
                }

                $ids = array_map(fn (CustomerSubscription $s): int => (int) $s->id, $chunk);
                self::dispatch($this->subscriptionTypeId, $this->perServerConcurrency, $ids)
                    ->delay(now()->addMinutes(5 * $waveIndex));

                Log::info('redeploy_outdated.wave_queued', [
                    'server_id' => $serverKey === 'none' ? null : $serverKey,
                    'wave' => $waveIndex,
                    'subscription_ids' => $ids,
                ]);
            }
        }

        Log::info('redeploy_outdated.completed', [
            'subscription_type_id' => $this->subscriptionTypeId,
            'scheduled' => $scheduled,
            'skipped' => $skipped,
        ]);
    }
}
