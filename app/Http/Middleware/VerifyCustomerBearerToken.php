<?php

namespace App\Http\Middleware;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use App\Support\CustomerSync\LmsHub;
use App\Support\UserSync\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            Log::debug('sync: customer bearer rejected, app_url missing', [
                'path' => $request->path(),
            ]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = $request->bearerToken();
        if (! is_string($token) || $token === '') {
            Log::debug('sync: customer bearer rejected, bearer token missing', [
                'path' => $request->path(),
                'app_url' => $appUrl,
            ]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $subscription = $this->findCustomerSubscriptionByUrl($appUrl);
        if (! $subscription) {
            Log::debug('sync: customer bearer rejected, no subscription for app_url', [
                'path' => $request->path(),
                'app_url' => $appUrl,
                'configured_hub_url' => LmsHub::configuredUrl(),
                'hub_url_matches' => LmsHub::matches($appUrl),
            ]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $subscription->loadMissing('customer');
        $customerToken = $subscription->customer?->token;

        $tokenValid = is_string($customerToken)
            && $customerToken !== ''
            && hash_equals($customerToken, $token);

        if (! $tokenValid && ! $this->lmsSyncTokenAccepted($subscription, $token)) {
            Log::debug('sync: customer bearer rejected, token mismatch', [
                'path' => $request->path(),
                'app_url' => $appUrl,
                'subscription_id' => $subscription->id,
                'subscription_type_id' => $subscription->subscription_type_id,
                'sync_token_configured' => filled(config('services.lms.sync_token')),
            ]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        Log::debug('sync: customer bearer accepted', [
            'path' => $request->path(),
            'app_url' => $appUrl,
            'subscription_id' => $subscription->id,
            'subscription_type_id' => $subscription->subscription_type_id,
            'via' => $tokenValid ? 'customer_token' : 'lms_sync_token',
        ]);

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
