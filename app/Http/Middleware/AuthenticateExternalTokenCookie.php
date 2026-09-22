<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateExternalTokenCookie
{
    /**
     * Promote the shared SSO cookie to a Bearer token when the request carries
     * no Authorization header. Lets the same-origin SPA (behind the /api proxy)
     * authenticate straight from the external_token cookie. Additive: a request
     * that already sends a Bearer token is untouched.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            $token = $request->cookie((string) config('sso.cookie', 'external_token'));

            if (is_string($token) && $token !== '') {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
