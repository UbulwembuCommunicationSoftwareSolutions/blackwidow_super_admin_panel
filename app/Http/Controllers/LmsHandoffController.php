<?php

namespace App\Http\Controllers;

use App\Models\CustomerUser;
use App\Services\LmsImpersonationService;
use App\Support\CustomerSync\LmsHub;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class LmsHandoffController extends Controller
{
    public function __invoke(Request $request, LmsImpersonationService $lms): RedirectResponse
    {
        $hubUrl = LmsHub::configuredUrl();

        if ($hubUrl === null) {
            return redirect()->away('/login?error=unavailable');
        }

        $unavailable = redirect()->away($hubUrl.'/admin/login?error=unavailable');

        $token = $request->cookie((string) config('sso.cookie'));
        $accessToken = is_string($token) && $token !== ''
            ? PersonalAccessToken::findToken($token)
            : null;
        $user = $accessToken?->tokenable;

        if (! $user instanceof CustomerUser || $user->isDeleteScheduled()) {
            return $unavailable;
        }

        if (! $user->lms_access && ! $user->is_system_admin) {
            return $unavailable;
        }

        try {
            $result = $lms->issueLink($user);
        } catch (\Throwable) {
            return $unavailable;
        }

        if (! $this->consumeUrlIsOnHub($result['impersonate_url'], $hubUrl)) {
            return $unavailable;
        }

        return redirect()->away($result['impersonate_url']);
    }

    private function consumeUrlIsOnHub(string $impersonateUrl, string $hubUrl): bool
    {
        $hubHost = parse_url($hubUrl, PHP_URL_HOST);
        $linkHost = parse_url($impersonateUrl, PHP_URL_HOST);

        return is_string($hubHost)
            && is_string($linkHost)
            && strcasecmp($hubHost, $linkHost) === 0;
    }
}
