<?php

namespace App\Observers;

use App\Jobs\SendDeploymentScriptJob;
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
        $this->pushDeploymentScriptWhenPinChanges($subscription);

        if ((int) $subscription->subscription_type_id !== 1) {
            return;
        }

        if (! $subscription->wasChanged(['panic_button_enabled', 'url', 'customer_id', 'subscription_type_id'])) {
            return;
        }

        CMSService::syncPanicButtonEnabled($subscription);
    }

    /**
     * Pinning or unpinning changes the site's target release, so its Forge deployment script
     * must be re-rendered to check out the new tag on the next deploy.
     */
    private function pushDeploymentScriptWhenPinChanges(CustomerSubscription $subscription): void
    {
        if (! $subscription->wasChanged('pinned_release_id')) {
            return;
        }

        if (! $subscription->server_id || ! $subscription->forge_site_id) {
            return;
        }

        $subscription->unsetRelation('pinnedRelease');

        if ($subscription->targetRelease() === null) {
            return;
        }

        SendDeploymentScriptJob::dispatch($subscription);
    }
}
