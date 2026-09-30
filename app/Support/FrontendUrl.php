<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Resolves which Reseller Console SPA origin a request came from, so links in
 * outgoing emails return the user to the branded host they were using.
 */
class FrontendUrl
{
    public static function forRequest(Request $request): string
    {
        $origin = rtrim(trim((string) $request->headers->get('Origin', '')), '/');

        if ($origin === '') {
            $referer = (string) $request->headers->get('Referer', '');
            $parts = parse_url($referer);

            if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
                $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            }
        }

        if ($origin !== '' && self::isAllowed($origin)) {
            return $origin;
        }

        return (string) config('frontend.url');
    }

    public static function passwordReset(Request $request, string $token, string $email): string
    {
        return self::forRequest($request)
            .config('frontend.password_reset_path')
            .'?'.http_build_query(['token' => $token, 'email' => $email]);
    }

    private static function isAllowed(string $origin): bool
    {
        if (in_array($origin, (array) config('frontend.urls'), true)) {
            return true;
        }

        return app()->isLocal()
            && preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#i', $origin) === 1;
    }
}
