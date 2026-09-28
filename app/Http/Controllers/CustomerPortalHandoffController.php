<?php

namespace App\Http\Controllers;

use App\Models\CustomerUser;
use App\Support\Sso\SsoHandoffLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class CustomerPortalHandoffController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $portal = $this->resolvePortal($request);
        $token = $request->cookie((string) config('sso.cookie'));
        $accessToken = is_string($token) && $token !== ''
            ? PersonalAccessToken::findToken($token)
            : null;
        $user = $accessToken?->tokenable;

        if (! $user instanceof CustomerUser || ! $user->is_system_admin) {
            SsoHandoffLog::refused('customer_portal', match (true) {
                ! is_string($token) || $token === '' => 'no_cookie',
                $accessToken === null => 'token_not_found',
                ! $user instanceof CustomerUser => 'not_customer_user',
                default => 'not_system_admin',
            }, $request, $user instanceof CustomerUser ? $user : null, ['target_host' => parse_url($portal, PHP_URL_HOST)]);

            return redirect()->away($portal.'/customer?error=unavailable');
        }

        $code = Str::random(64);
        Cache::put('customer-portal-sso:'.$code, $token, now()->addMinute());

        SsoHandoffLog::redirected('customer_portal', $request, $user, $portal);

        return redirect()->away($portal.'/customer?sso='.$code);
    }

    /**
     * Pick the customer-portal frontend to return the user to.
     *
     * A reverse proxy in front of this app (e.g. superadmin.aims.net.za) may set
     * the X-Sso-Portal header so an aims.net.za user lands back on the aims.net.za
     * frontend instead of the default. The value is only honoured when it is in
     * the configured allow-list, so the header can never become an open redirect.
     */
    private function resolvePortal(Request $request): string
    {
        $default = rtrim((string) config('sso.customer_portal_url'), '/');
        $requested = rtrim((string) $request->header('X-Sso-Portal', ''), '/');

        if ($requested !== '' && in_array($requested, (array) config('sso.customer_portal_urls', []), true)) {
            return $requested;
        }

        return $default;
    }
}
