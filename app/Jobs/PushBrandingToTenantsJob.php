<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Services\BrandingSync\TenantBrandingPusher;
use App\Support\BrandingSync\BrandingSyncPayload;
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
        public ?int $subscriptionId = null,
        public array $cmsSlots = ['login_logo', 'menu_logo', 'login_background'],
        public ?int $customerId = null,
    ) {
        if ($this->subscriptionId === null && $this->customerId === null) {
            throw new \InvalidArgumentException('PushBrandingToTenantsJob requires subscriptionId or customerId.');
        }
    }

    public function handle(TenantBrandingPusher $pusher): void
    {
        $cmsSlots = array_values(array_intersect($this->cmsSlots, BrandingSyncPayload::SLOTS));
        if ($cmsSlots === []) {
            return;
        }

        if ($this->customerId !== null) {
            $customer = Customer::query()->find($this->customerId);
            if (! $customer) {
                Log::warning('Branding push skipped: customer no longer exists', [
                    'customer_id' => $this->customerId,
                ]);

                return;
            }

            $pusher->pushCustomerDefaultsToTenants($customer, $cmsSlots);
            $pusher->pushCustomerDefaultsToLmsHub($customer, $cmsSlots);

            return;
        }

        $subscription = CustomerSubscription::query()->find($this->subscriptionId);

        if (! $subscription) {
            Log::warning('Branding push skipped: subscription no longer exists', [
                'subscription_id' => $this->subscriptionId,
            ]);

            return;
        }

        $pusher->pushSlots($subscription, $cmsSlots);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Branding push to tenants failed permanently', [
            'subscription_id' => $this->subscriptionId,
            'customer_id' => $this->customerId,
            'slots' => $this->cmsSlots,
            'error' => $exception->getMessage(),
        ]);
    }
}
