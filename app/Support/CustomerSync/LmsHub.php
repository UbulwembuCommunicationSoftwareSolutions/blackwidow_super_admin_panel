<?php

namespace App\Support\CustomerSync;

use App\Support\UserSync\TenantResolver;

/**
 * The shared LMS is one deployment, identified by LMS_HUB_URL.
 * It is not a per-customer subscription.
 */
final class LmsHub
{
    public static function configuredUrl(): ?string
    {
        $url = config('services.lms.hub_url');

        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        return rtrim(trim($url), '/');
    }

    public static function matches(?string $appUrl): bool
    {
        $configured = self::configuredUrl();

        if ($configured === null) {
            return false;
        }

        return TenantResolver::normalise($configured) === TenantResolver::normalise($appUrl);
    }
}
