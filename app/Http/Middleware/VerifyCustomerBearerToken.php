<?php

namespace App\Http\Middleware;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
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

        $tokenValid = is_string($customerToken)
            && $customerToken !== ''
            && hash_equals($customerToken, $token);

        if (! $tokenValid && ! $this->lmsSyncTokenAccepted($subscription, $token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->attributes->set('customer_subscription', $subscription);

        return $next($request);
    }

    /**
     * Shared LMS hubs authenticate outbound sync with LMS_SYNC_TOKEN
     * (matches the LMS SECURE_TOKEN), not only the owning customer's token.
     */
    private function lmsSyncTokenAccepted(CustomerSubscription $subscription, string $token): bool
    {
        if ((int) $subscription->subscription_type_id !== SubscriptionType::LMS_TYPE_ID) {
            return false;
        }

        $configured = config('services.lms.sync_token');

        return is_string($configured)
            && $configured !== ''
            && hash_equals($configured, $token);
    }

    private function findCustomerSubscriptionByUrl(string $appUrl): ?CustomerSubscription
    {
        return TenantResolver::resolveSubscription($appUrl);
    }
}
