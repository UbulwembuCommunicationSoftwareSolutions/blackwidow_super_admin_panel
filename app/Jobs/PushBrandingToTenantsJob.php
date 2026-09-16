<?php

namespace App\Jobs;

use App\Models\CustomerSubscription;
use App\Services\BrandingSync\TenantBrandingPusher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PushBrandingToTenantsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * @param  list<string>  $cmsSlots  CMS slot names: login_logo, menu_logo, login_background
     */
    public function __construct(
        public int $subscriptionId,
        public array $cmsSlots = ['login_logo', 'menu_logo', 'login_background'],
    ) {}

    public function handle(TenantBrandingPusher $pusher): void
    {
        $subscription = CustomerSubscription::query()->find($this->subscriptionId);

        if (! $subscription) {
            Log::warning('Branding push skipped: subscription no longer exists', [
                'subscription_id' => $this->subscriptionId,
            ]);

            return;
        }

        $pusher->pushSlots($subscription, $this->cmsSlots);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Branding push to tenants failed permanently', [
            'subscription_id' => $this->subscriptionId,
            'slots' => $this->cmsSlots,
            'error' => $exception->getMessage(),
        ]);
    }
}
