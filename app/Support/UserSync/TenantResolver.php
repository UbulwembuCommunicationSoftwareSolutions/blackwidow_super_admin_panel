<?php

namespace App\Support\UserSync;

use App\Models\CustomerSubscription;

/**
 * Resolves the tenant app that a sync request came from, via its app_url.
 *
 * A substring match alone is not enough: a customer with both acme.co.za and
 * firearm.acme.co.za would resolve either one arbitrarily, and the resolved
 * subscription decides which access flag a new user is granted. An exact match
 * on the normalised host therefore always wins, with the looser match kept only
 * as a fallback for URLs stored with extra path segments.
 */
class TenantResolver
{
    public static function normalise(?string $url): string
    {
        if (! is_string($url)) {
            return '';
        }

        $normalised = preg_replace('#^https?://#i', '', trim($url));

        return strtolower(rtrim((string) $normalised, '/'));
    }

    public static function resolveSubscription(?string $appUrl): ?CustomerSubscription
    {
        $needle = self::normalise($appUrl);

        if ($needle === '') {
            return null;
        }

        $candidates = CustomerSubscription::query()
            ->where('url', 'LIKE', '%'.$needle.'%')
            ->get();

        $exact = $candidates->first(fn (CustomerSubscription $subscription) => self::normalise($subscription->url) === $needle);

        return $exact ?? $candidates->first();
    }
}
