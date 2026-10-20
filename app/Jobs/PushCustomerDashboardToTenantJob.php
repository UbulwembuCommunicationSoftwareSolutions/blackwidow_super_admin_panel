<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Reporting\Aims\TenantExternalDashboardPusher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Tells one CMS to add or remove an External Dashboard for an AIMS grant.
 */
class PushCustomerDashboardToTenantJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public string $syncKey,
        public string $heading,
        public string $description,
        public bool $enabled,
        public string $url,
        public string $token,
    ) {}

    public function handle(TenantExternalDashboardPusher $pusher): void
    {
        if (! config('user_sync.enabled', true)) {
            return;
        }

        $pusher->push(
            $this->syncKey,
            $this->heading,
            $this->description,
            $this->enabled,
            $this->url,
            $this->token,
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AIMS dashboard push to the CMS failed permanently', [
            'sync_key' => $this->syncKey,
            'url' => $this->url,
            'error' => $exception->getMessage(),
        ]);
    }
}
