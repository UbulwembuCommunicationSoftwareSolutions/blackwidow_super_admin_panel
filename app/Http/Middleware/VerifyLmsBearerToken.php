<?php

namespace App\Http\Middleware;

use App\Support\CustomerSync\LmsHub;
use App\Support\CustomerSync\LmsTenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyLmsBearerToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appUrl = $request->input('app_url');
        if (! is_string($appUrl) || $appUrl === '') {
            Log::debug('sync: LMS bearer rejected, app_url missing', [
                'path' => $request->path(),
            ]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (! LmsTenantResolver::hasHubAtAppUrl($appUrl)) {
            Log::debug('sync: LMS bearer rejected, app_url is not the hub', [
                'path' => $request->path(),
                'app_url' => $appUrl,
                'configured_hub_url' => LmsHub::configuredUrl(),
                'sync_token_configured' => filled(config('services.lms.sync_token')),
            ]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = $request->bearerToken();
        if (! is_string($token) || $token === '') {
            Log::debug('sync: LMS bearer rejected, bearer token missing', [
                'path' => $request->path(),
                'app_url' => $appUrl,
            ]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($this->tokenMatchesConfiguredSyncSecret($token)) {
            Log::debug('sync: LMS bearer accepted via LMS_SYNC_TOKEN', [
                'path' => $request->path(),
                'app_url' => $appUrl,
            ]);

            return $next($request);
        }

        if ($this->tokenMatchesCustomerAtHub($appUrl, $token)) {
            Log::debug('sync: LMS bearer accepted via customer token', [
                'path' => $request->path(),
                'app_url' => $appUrl,
            ]);

            return $next($request);
        }

        Log::debug('sync: LMS bearer rejected, token does not match LMS_SYNC_TOKEN', [
            'path' => $request->path(),
            'app_url' => $appUrl,
            'sync_token_configured' => filled(config('services.lms.sync_token')),
        ]);

        return response()->json(['message' => 'Unauthorized'], 401);
    }

    private function tokenMatchesConfiguredSyncSecret(string $token): bool
    {
        $configured = config('services.lms.sync_token');

        if (! is_string($configured) || $configured === '') {
            return false;
        }

        return hash_equals($configured, $token);
    }

    private function tokenMatchesCustomerAtHub(string $appUrl, string $token): bool
    {
        return LmsTenantResolver::subscriptionsAtAppUrl($appUrl)
            ->contains(function ($subscription) use ($token): bool {
                $customerToken = $subscription->customer?->token;

                return is_string($customerToken)
                    && $customerToken !== ''
                    && hash_equals($customerToken, $token);
            });
    }
}
