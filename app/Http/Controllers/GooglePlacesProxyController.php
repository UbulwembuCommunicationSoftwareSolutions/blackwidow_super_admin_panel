<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class GooglePlacesProxyController extends Controller
{
    /**
     * Legacy Places web-service paths this proxy will call.
     *
     * @var list<string>
     */
    private const ALLOWED_ENDPOINTS = [
        'place/autocomplete/json',
        'place/details/json',
        'place/findplacefromtext/json',
        'place/photo',
        'place/nearbysearch/json',
        'place/textsearch/json',
    ];

    /**
     * Lookups whose result is stable enough to cache. Autocomplete is cached
     * briefly so an identical retry loop does not spend the credit again.
     *
     * @var array<string, int> endpoint => cache minutes
     */
    private const CACHE_MINUTES = [
        'place/autocomplete/json' => 1,
        'place/details/json' => 1440,
        'place/findplacefromtext/json' => 1440,
        'place/nearbysearch/json' => 1440,
        'place/textsearch/json' => 1440,
    ];

    public function proxy(Request $request): Response
    {
        $endpoint = ltrim((string) $request->input('endpoint', 'place/autocomplete/json'), '/');

        if (! in_array($endpoint, self::ALLOWED_ENDPOINTS, true)) {
            return response()->json([
                'status' => 'INVALID_REQUEST',
                'error_message' => 'Unsupported Places endpoint.',
            ], 422);
        }

        $params = $request->except(['endpoint', 'key']);
        $params['key'] = (string) config('services.google.places_api_key');

        $referer = $this->callerReferer($request);
        $domain = $this->domainFromReferer($referer);
        $cacheMinutes = self::CACHE_MINUTES[$endpoint] ?? null;
        $cacheKey = 'google_places:'.$domain.':'.hash('sha256', $endpoint.'|'.json_encode($this->cacheableParams($params)));

        if ($cacheMinutes !== null) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return response($cached['body'], $cached['status'])
                    ->header('Content-Type', $cached['content_type']);
            }
        }

        $pending = Http::timeout(15)->acceptJson();
        if ($referer !== null) {
            $pending = $pending->withHeaders(['Referer' => $referer]);
        }

        $started = microtime(true);
        $response = $pending->get('https://maps.googleapis.com/maps/api/'.$endpoint, $params);
        $latencyMs = (int) ((microtime(true) - $started) * 1000);
        $status = $response->json('status');

        Log::info('google_places.usage', [
            'domain' => $domain,
            'method' => $endpoint,
            'status' => is_string($status) ? $status : null,
            'http_status' => $response->status(),
            'latency_ms' => $latencyMs,
        ]);

        $contentType = $response->header('Content-Type') ?? 'application/json';

        if ($cacheMinutes !== null && $status === 'OK') {
            Cache::put($cacheKey, [
                'body' => $response->body(),
                'status' => $response->status(),
                'content_type' => $contentType,
            ], now()->addMinutes($cacheMinutes));
        }

        return response($response->body(), $response->status())
            ->header('Content-Type', $contentType);
    }

    private function callerReferer(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');
        $referer = $request->headers->get('Referer');
        $value = is_string($origin) && $origin !== '' ? $origin : $referer;

        if (! is_string($value) || $value === '') {
            return null;
        }

        $parts = parse_url($value);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] : 'https';

        return $scheme.'://'.$parts['host'].'/';
    }

    private function domainFromReferer(?string $referer): string
    {
        if ($referer === null) {
            return 'unknown';
        }

        $host = parse_url($referer, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'unknown';
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function cacheableParams(array $params): array
    {
        unset($params['key']);
        ksort($params);

        return $params;
    }
}
