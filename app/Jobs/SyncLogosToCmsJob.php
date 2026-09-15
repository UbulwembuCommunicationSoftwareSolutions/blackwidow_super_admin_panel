<?php

namespace App\Jobs;

use App\Models\CustomerSubscription;
use App\Services\CMSService;
use App\Services\LogoSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncLogosToCmsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $slots
     */
    public function __construct(
        public int $subscriptionId,
        public array $slots = ['logo_1', 'logo_2', 'logo_3'],
    ) {}

    public function handle(): void
    {
        $subscription = CustomerSubscription::query()->find($this->subscriptionId);
        if (! $subscription) {
            Log::warning('SyncLogosToCmsJob: subscription not found', [
                'subscription_id' => $this->subscriptionId,
            ]);

            return;
        }

        if ((int) $subscription->subscription_type_id !== 1) {
            return;
        }

        $payload = LogoSyncService::buildCmsPayload($subscription, $this->slots);
        CMSService::syncLogos($subscription, $payload);
    }
}
