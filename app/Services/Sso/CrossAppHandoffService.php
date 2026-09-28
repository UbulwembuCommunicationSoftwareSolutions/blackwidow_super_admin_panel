<?php

namespace App\Services\Sso;

use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Services\CMSService;
use App\Services\LmsImpersonationService;
use App\Support\CustomerSync\LmsHub;
use App\Support\Sso\SsoHandoffLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sends a user from one suite app to another through Super Admin: the shared
 * `external_token` cookie identifies them, and the target app mints a one-time
 * sign-in link. When that is not possible the user is sent to the target app
 * unauthenticated (its login page) and the reason is logged.
 */
class CrossAppHandoffService
{
    /** Subscription types that expose `/admin-api/impersonate` via CMSService. */
    private const TENANT_LINK_TYPES = [1, 2];

    private const LMS_TYPE = 12;

    public function __construct(
        private readonly LmsImpersonationService $lms,
        private readonly CMSService $tenants,
    ) {}

    /**
     * The URL to send the user to, or null when the target is not a known suite app.
     */
    public function redirectUrl(Request $request, string $targetUrl, string $handoff): ?string
    {
        $targetHost = self::hostOf($targetUrl);

        if ($targetHost === null || ! $this->isKnownHost($targetHost)) {
            SsoHandoffLog::refused($handoff, 'unknown_target', $request, extra: ['target_host' => $targetHost]);

            return null;
        }

        $fallback = self::baseUrl($targetUrl);
        $refuse = function (string $reason, ?CustomerUser $user = null, array $extra = []) use ($request, $handoff, $targetHost, $fallback): string {
            SsoHandoffLog::refused($handoff, $reason, $request, $user, array_merge(['target_host' => $targetHost], $extra));

            return $fallback;
        };

        $token = $request->cookie((string) config('sso.cookie'));

        if (! is_string($token) || $token === '') {
            return $refuse('no_cookie');
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if ($accessToken === null) {
            return $refuse('token_not_found');
        }

        $user = $accessToken->tokenable;

        if (! $user instanceof CustomerUser) {
            return $refuse('not_customer_user', extra: ['tokenable_type' => $accessToken->tokenable_type]);
        }

        if ($user->isDeleteScheduled()) {
            return $refuse('delete_scheduled', $user);
        }

        if ($this->isLmsHost($targetHost)) {
            if (! $user->lms_access && ! $user->is_system_admin) {
                return $refuse('no_access', $user, ['subscription_type_id' => self::LMS_TYPE]);
            }

            return $this->follow(
                fn (): array => $this->lms->issueLink($user),
                $request, $handoff, $user, $targetHost, $refuse,
            );
        }

        $subscription = $this->subscriptionsForHost($targetHost)
            ->first(fn (CustomerSubscription $subscription): bool => (int) $subscription->customer_id === (int) $user->customer_id);

        if ($subscription === null) {
            return $refuse('no_subscription', $user);
        }

        $typeContext = ['subscription_type_id' => (int) $subscription->subscription_type_id];

        if (! $user->checkAccess($subscription->subscription_type_id)) {
            return $refuse('no_access', $user, $typeContext);
        }

        if (! in_array((int) $subscription->subscription_type_id, self::TENANT_LINK_TYPES, true)) {
            return $refuse('unsupported_type', $user, $typeContext);
        }

        return $this->follow(
            fn (): array => $this->tenants->impersonate($user, $subscription),
            $request, $handoff, $user, $targetHost, $refuse,
        );
    }

    /**
     * @param  callable(): array{impersonate_url: string, expires_in_minutes: int}  $issue
     * @param  callable(string, ?CustomerUser=, array<string, mixed>=): string  $refuse
     */
    private function follow(callable $issue, Request $request, string $handoff, CustomerUser $user, string $targetHost, callable $refuse): string
    {
        try {
            $consumeUrl = $issue()['impersonate_url'];
        } catch (\Throwable $e) {
            return $refuse('link_request_failed', $user, ['error' => $e->getMessage()]);
        }

        if (strcasecmp((string) self::hostOf($consumeUrl), $targetHost) !== 0) {
            return $refuse('consume_url_off_host', $user, ['consume_host' => self::hostOf($consumeUrl)]);
        }

        SsoHandoffLog::redirected($handoff, $request, $user, $consumeUrl);

        return $consumeUrl;
    }

    private function isKnownHost(string $host): bool
    {
        return $this->isLmsHost($host) || $this->subscriptionsForHost($host)->isNotEmpty();
    }

    private function isLmsHost(string $host): bool
    {
        $hubHost = self::hostOf((string) LmsHub::configuredUrl());

        return $hubHost !== null && strcasecmp($hubHost, $host) === 0;
    }

    /**
     * @return Collection<int, CustomerSubscription>
     */
    private function subscriptionsForHost(string $host): Collection
    {
        return CustomerSubscription::query()
            ->where('url', 'like', '%'.$host.'%')
            ->get()
            ->filter(fn (CustomerSubscription $subscription): bool => strcasecmp((string) self::hostOf((string) $subscription->url), $host) === 0)
            ->values();
    }

    private static function hostOf(string $url): ?string
    {
        $host = self::parse($url)['host'] ?? null;

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    private static function baseUrl(string $url): string
    {
        $parts = self::parse($url);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].$port;
    }

    /**
     * Subscription URLs are not always stored with a scheme.
     *
     * @return array{scheme?: string, host?: string, port?: int}
     */
    private static function parse(string $url): array
    {
        $url = trim($url);

        if ($url !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        return is_array($parts) ? $parts : [];
    }
}
