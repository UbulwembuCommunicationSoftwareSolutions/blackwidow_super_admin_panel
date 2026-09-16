<?php

namespace App\Observers;

use App\Jobs\SendSubscriptionEmailJob;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Support\UserSync\UserSyncPayload;

class CustomerUserObserver
{
    /**
     * Users created from the console (as opposed to synced in from a tenant
     * app) must be able to log into the console itself.
     */
    public function creating(CustomerUser $customerUser): void
    {
        if (! $customerUser->skip_sync) {
            $customerUser->console_access = true;
        }
    }

    /**
     * Send the password reset/login email for every system the user has
     * access to.
     */
    public function created(CustomerUser $customerUser): void
    {
        foreach (UserSyncPayload::ACCESS_FLAGS as $flag => $subscriptionTypeId) {
            if (! $customerUser->{$flag}) {
                continue;
            }

            if ($subscriptionTypeId === 1) {
                SendWelcomeEmailJob::dispatch($customerUser);

                continue;
            }

            $subscription = CustomerSubscription::where('customer_id', $customerUser->customer_id)
                ->where('subscription_type_id', $subscriptionTypeId)
                ->first();

            if ($subscription) {
                SendSubscriptionEmailJob::dispatch($customerUser, $subscription);
            }
        }
    }
}
