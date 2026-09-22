<?php

namespace App\Http\Middleware;

use App\Support\CustomerSync\LmsTenantResolver;
use Closure;
use Illuminate\Http\Request;
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
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (! LmsTenantResolver::hasHubAtAppUrl($appUrl)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = $request->bearerToken();
        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($this->tokenMatchesConfiguredSyncSecret($token)) {
            return $next($request);
        }

        if ($this->tokenMatchesCustomerAtHub($appUrl, $token)) {
            return $next($request);
        }

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
