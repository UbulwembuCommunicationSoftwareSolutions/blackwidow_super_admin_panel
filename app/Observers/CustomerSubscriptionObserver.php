<?php

namespace App\Observers;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionTypeRelease;
use App\Services\CMSService;

class CustomerSubscriptionObserver
{
    public function saving(CustomerSubscription $subscription): void
    {
        if (! $subscription->isDirty('deployed_release_id') || ! $subscription->deployed_release_id) {
            return;
        }

        $tag = SubscriptionTypeRelease::query()
            ->whereKey($subscription->deployed_release_id)
            ->value('tag');

        if (is_string($tag) && $tag !== '') {
            $subscription->deployed_version = $tag;
        }
    }

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
