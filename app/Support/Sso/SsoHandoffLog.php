<?php

namespace App\Support\Sso;

use App\Models\CustomerUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Audit trail for the cookie sign-in handoffs (LMS, customer portal). Every
 * outcome is logged with a reason so a refused redirect can be explained.
 * Tokens, one-time codes and consume URLs are never logged: only the host a
 * user is sent to.
 */
final class SsoHandoffLog
{
    public const REDIRECTED = 'sso.handoff.redirected';

    public const REFUSED = 'sso.handoff.refused';

    public static function redirected(string $handoff, Request $request, ?CustomerUser $user, string $redirectTo): void
    {
        Log::info(self::REDIRECTED, array_merge(self::context($handoff, $request, $user), [
            'redirect_host' => parse_url($redirectTo, PHP_URL_HOST),
        ]));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function refused(string $handoff, string $reason, Request $request, ?CustomerUser $user = null, array $extra = []): void
    {
        Log::warning(self::REFUSED, array_merge(self::context($handoff, $request, $user), [
            'reason' => $reason,
        ], $extra));
    }

    /**
     * @return array{handoff: string, customer_user_id: int|null, customer_id: int|null, has_cookie: bool, ip: string|null, user_agent: string|null}
     */
    private static function context(string $handoff, Request $request, ?CustomerUser $user): array
    {
        $cookie = $request->cookie((string) config('sso.cookie'));

        return [
            'handoff' => $handoff,
            'customer_user_id' => $user?->id,
            'customer_id' => $user?->customer_id,
            'has_cookie' => is_string($cookie) && $cookie !== '',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }
}
