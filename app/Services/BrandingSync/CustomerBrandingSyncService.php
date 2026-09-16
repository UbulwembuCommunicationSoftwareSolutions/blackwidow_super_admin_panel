<?php

namespace App\Services\BrandingSync;

use App\Models\CustomerSubscription;
use App\Services\LogoSyncService;
use App\Support\BrandingSync\BrandingSyncOutcome;
use App\Support\BrandingSync\BrandingSyncPayload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Applies an inbound branding payload from a tenant onto a CustomerSubscription.
 */
class CustomerBrandingSyncService
{
    /**
     * @return array{outcome: BrandingSyncOutcome, payload: BrandingSyncPayload}
     */
    public function upsertFromTenant(CustomerSubscription $subscription, BrandingSyncPayload $incoming): array
    {
        $saSlot = $incoming->saSlot();
        if ($saSlot === null) {
            throw new \InvalidArgumentException('Unknown branding slot: '.$incoming->slot);
        }

        $local = BrandingSyncPayload::fromSubscription($subscription, $incoming->slot);

        if (
            filled($incoming->checksum)
            && filled($local->checksum)
            && hash_equals((string) $local->checksum, (string) $incoming->checksum)
            && $incoming->cleared === $local->cleared
        ) {
            return ['outcome' => BrandingSyncOutcome::Unchanged, 'payload' => $local];
        }

        $remoteTs = $incoming->updatedAt ?? now();
        $localTs = $local->updatedAt;

        if ($localTs && $remoteTs->lt($localTs)) {
            return ['outcome' => BrandingSyncOutcome::Stale, 'payload' => $local];
        }

        $hadLocal = filled($subscription->getAttribute($saSlot));
        $subscription->skipSync = true;

        if ($incoming->cleared) {
            $this->clearSlot($subscription, $saSlot, $remoteTs);

            return [
                'outcome' => BrandingSyncOutcome::Cleared,
                'payload' => BrandingSyncPayload::fromSubscription($subscription->fresh(), $incoming->slot),
            ];
        }

        if (! filled($incoming->url)) {
            throw new \InvalidArgumentException('Branding url is required when cleared is false.');
        }

        $contents = $this->download($incoming->url);
        $checksum = BrandingSyncPayload::computeChecksum($contents);

        if (filled($incoming->checksum) && ! hash_equals($checksum, (string) $incoming->checksum)) {
            throw new \RuntimeException('Branding checksum mismatch for slot '.$incoming->slot);
        }

        $this->storeSlot($subscription, $saSlot, $contents, $checksum, $remoteTs);

        $fresh = BrandingSyncPayload::fromSubscription($subscription->fresh(), $incoming->slot);

        return [
            'outcome' => $hadLocal ? BrandingSyncOutcome::Updated : BrandingSyncOutcome::Created,
            'payload' => $fresh,
        ];
    }

    private function clearSlot(CustomerSubscription $subscription, string $saSlot, mixed $remoteTs): void
    {
        $disk = Storage::disk('public');
        $oldPath = $subscription->getAttribute($saSlot);

        $subscription->fill([
            $saSlot => null,
            LogoSyncService::timestampColumn($saSlot) => $remoteTs,
            BrandingSyncPayload::checksumColumn($saSlot) => null,
        ]);
        $subscription->save();

        if (filled($oldPath)) {
            $disk->delete($oldPath);
        }
    }

    private function storeSlot(
        CustomerSubscription $subscription,
        string $saSlot,
        string $contents,
        string $checksum,
        mixed $remoteTs,
    ): void {
        $disk = Storage::disk('public');
        $oldPath = $subscription->getAttribute($saSlot);
        $extension = $this->guessExtension($contents) ?? 'bin';
        $newPath = Str::uuid()->toString().'.'.$extension;
        $disk->put($newPath, $contents);

        $subscription->fill([
            $saSlot => $newPath,
            LogoSyncService::timestampColumn($saSlot) => $remoteTs,
            BrandingSyncPayload::checksumColumn($saSlot) => $checksum,
        ]);
        $subscription->save();

        if (filled($oldPath) && $oldPath !== $newPath) {
            $disk->delete($oldPath);
        }
    }

    private function download(string $url): string
    {
        $response = Http::timeout((int) config('branding_sync.timeout', 30))
            ->connectTimeout(10)
            ->get($url);

        if (! $response->successful()) {
            Log::error('Failed to download branding asset', [
                'url' => $url,
                'status' => $response->status(),
            ]);

            throw new \RuntimeException('Failed to download branding asset from '.$url);
        }

        $body = $response->body();
        if ($body === '') {
            throw new \RuntimeException('Downloaded branding asset was empty: '.$url);
        }

        return $body;
    }

    private function guessExtension(string $contents): ?string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($contents) ?: '';

        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => null,
        };
    }
}
