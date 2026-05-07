<?php

namespace App\Observers;

use App\Models\CustomerSubscription;
use App\Services\CMSService;

class CustomerSubscriptionObserver
{
    public function created(CustomerSubscription $subscription): void
    {
        if ((int) $subscription->subscription_type_id !== 1) {
            return;
        }

        CMSService::syncPanicButtonEnabled($subscription);
    }

    public function updated(CustomerSubscription $subscription): void
    {
        if ((int) $subscription->subscription_type_id !== 1) {
            return;
        }

        if (! $subscription->wasChanged(['panic_button_enabled', 'url', 'customer_id', 'subscription_type_id'])) {
            return;
        }

        CMSService::syncPanicButtonEnabled($subscription);
    }
}
