<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Embed tokens may call only the report routes for the dashboard they were minted for.
 * Every other backend route rejects them before the customer-admin middleware can delete them.
 */
class AimsEmbedGuard
{
    public const NAME = 'aims-embed';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'reject'): Response
    {
        $token = $request->user()?->currentAccessToken();
        $isEmbed = $token !== null && ($token->name ?? null) === self::NAME;

        if ($mode === 'reject') {
            if ($isEmbed) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return $next($request);
        }

        if ($isEmbed) {
            $id = (string) $request->route('id');

            if ($id === '' || ! $request->user()->tokenCan('embed-dashboard:'.$id)) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return $next($request);
        }

        return app(EnsureActiveCustomerAdmin::class)->handle($request, $next);
    }
}
