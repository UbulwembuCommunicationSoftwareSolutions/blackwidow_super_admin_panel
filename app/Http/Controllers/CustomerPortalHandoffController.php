<?php

namespace App\Http\Controllers;

use App\Models\CustomerUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class CustomerPortalHandoffController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $portal = rtrim((string) config('sso.customer_portal_url'), '/');
        $token = $request->cookie((string) config('sso.cookie'));
        $accessToken = is_string($token) && $token !== ''
            ? PersonalAccessToken::findToken($token)
            : null;
        $user = $accessToken?->tokenable;

        if (! $user instanceof CustomerUser || ! $user->is_system_admin) {
            return redirect()->away($portal.'/customer?error=unavailable');
        }

        $code = Str::random(64);
        Cache::put('customer-portal-sso:'.$code, $token, now()->addMinute());

        return redirect()->away($portal.'/customer?sso='.$code);
    }
}
