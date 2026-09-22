<?php

namespace App\Support\CustomerSync;

use App\Models\CustomerSubscription;
use App\Support\UserSync\TenantResolver;
use Illuminate\Support\Collection;

/**
 * Resolves LMS hub subscriptions for a tenant app_url (many customers may share one LMS URL).
 */
class LmsTenantResolver
{
    /**
     * @return Collection<int, CustomerSubscription>
     */
    public static function subscriptionsAtAppUrl(?string $appUrl, ?int $subscriptionTypeId = null): Collection
    {
        $needle = TenantResolver::normalise($appUrl);

        if ($needle === '') {
            return collect();
        }

        $typeId = $subscriptionTypeId ?? (int) config('customer_sync.lms_subscription_type_id');

        return CustomerSubscription::query()
            ->where('subscription_type_id', $typeId)
            ->where('url', 'LIKE', '%'.$needle.'%')
            ->with('customer')
            ->get()
            ->filter(fn (CustomerSubscription $subscription) => TenantResolver::normalise($subscription->url) === $needle)
            ->values();
    }

    public static function hasHubAtAppUrl(?string $appUrl): bool
    {
        return self::subscriptionsAtAppUrl($appUrl)->isNotEmpty();
    }
}
