<?php

namespace App\Http\Middleware;

use App\Models\CustomerSubscription;
use App\Support\UserSync\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCustomerBearerToken
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

        $token = $request->bearerToken();
        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $subscription = $this->findCustomerSubscriptionByUrl($appUrl);
        if (! $subscription) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $subscription->loadMissing('customer');
        $customerToken = $subscription->customer?->token;

        if (! is_string($customerToken) || $customerToken === '' || ! hash_equals($customerToken, $token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->attributes->set('customer_subscription', $subscription);

        return $next($request);
    }

    private function findCustomerSubscriptionByUrl(string $appUrl): ?CustomerSubscription
    {
        return TenantResolver::resolveSubscription($appUrl);
    }
}
