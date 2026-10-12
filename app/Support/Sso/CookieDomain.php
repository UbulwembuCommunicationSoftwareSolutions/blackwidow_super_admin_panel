<?php

namespace App\Support\Sso;

/**
 * Works out the COOKIE_DOMAIN a tenant site needs for the shared SSO cookie.
 *
 * The cookie can only be shared across hosts under one registrable domain, so
 * the value is that domain with a leading dot. A browser drops a cookie whose
 * domain does not cover the host that set it, so a site must never be given
 * another domain's value.
 */
final class CookieDomain
{
    /** Second-level labels that sit under a two-letter country TLD (co.za, org.za, …). */
    private const COUNTRY_SECOND_LEVELS = ['ac', 'co', 'com', 'edu', 'gov', 'net', 'org', 'web'];

    /** Development hosts: left host-only so local apps do not share the cookie. */
    private const LOCAL_TLDS = ['test', 'local', 'localhost', 'invalid', 'example'];

    /** Hosting domains shared between unrelated sites: no parent-domain cookie. */
    private const SHARED_HOSTING = ['on-forge.com', 'laravel.cloud', 'vapor-farm.com'];

    /**
     * `.example.org.za` for a site URL, or null when the host has no parent
     * domain that is safe to share a cookie on.
     */
    public static function forUrl(?string $url): ?string
    {
        $host = self::host($url);

        if ($host === null || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        foreach (self::SHARED_HOSTING as $shared) {
            if ($host === $shared || str_ends_with($host, '.'.$shared)) {
                return null;
            }
        }

        $labels = explode('.', $host);
        $count = count($labels);

        if ($count < 2 || in_array($labels[$count - 1], self::LOCAL_TLDS, true)) {
            return null;
        }

        $underCountrySecondLevel = $count >= 3
            && strlen($labels[$count - 1]) === 2
            && in_array($labels[$count - 2], self::COUNTRY_SECOND_LEVELS, true);

        return '.'.implode('.', array_slice($labels, $underCountrySecondLevel ? -3 : -2));
    }

    public static function host(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
