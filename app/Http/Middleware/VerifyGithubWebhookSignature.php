<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyGithubWebhookSignature
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.github.webhook_secret');
        if (blank($secret)) {
            return response()->json(['message' => 'GitHub webhook is not configured.'], 503);
        }

        $signature = $request->header('X-Hub-Signature-256');
        if (! is_string($signature) || ! str_starts_with($signature, 'sha256=')) {
            return response()->json(['message' => 'Missing or invalid signature.'], 401);
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        return $next($request);
    }
}
