<?php

declare(strict_types=1);

namespace App\Services\Reporting\Aims;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pushes one AIMS grant to the CMS that owns the subscription, using the
 * customer SECURE_TOKEN the same way user sync does.
 */
class TenantExternalDashboardPusher
{
    public function push(
        string $syncKey,
        string $heading,
        string $description,
        bool $enabled,
        string $url,
        string $token,
    ): void {
        $url = rtrim($url, '/');

        if ($url === '' || $token === '') {
            Log::info('AIMS dashboard push skipped: subscription has no URL or token', [
                'sync_key' => $syncKey,
            ]);

            return;
        }

        $endpoint = $url.'/admin-api/v1/sync/external-dashboards';

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('user_sync.timeout', 15))
                ->connectTimeout(10)
                ->post($endpoint, [
                    'app_url' => $url,
                    'origin' => 'super_admin',
                    'sync_key' => $syncKey,
                    'heading' => $heading,
                    'description' => $description,
                    'enabled' => $enabled,
                ]);
        } catch (\Throwable $e) {
            Log::error('AIMS dashboard push to the CMS threw', [
                'sync_key' => $syncKey,
                'url' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        if (! $response->successful()) {
            Log::error('AIMS dashboard push to the CMS failed', [
                'sync_key' => $syncKey,
                'url' => $endpoint,
                'status' => $response->status(),
            ]);

            throw new RuntimeException('CMS external dashboard sync failed with HTTP '.$response->status().' for '.$endpoint);
        }
    }
}
