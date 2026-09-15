<?php

namespace App\Services;

use App\Jobs\SyncLogosToCmsJob;
use App\Models\CustomerSubscription;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LogoSyncService
{
    /** CMS branding keys keyed by SuperAdmin logo slot. */
    public const SLOT_TO_CMS = [
        'logo_1' => 'login_logo',
        'logo_2' => 'menu_logo',
        'logo_3' => 'login_background',
    ];

    public static function timestampColumn(string $slot): string
    {
        return $slot.'_updated_at';
    }

    /**
     * Stamp logo slot timestamps and optionally push to CMS.
     *
     * @param  list<string>  $slots
     */
    public static function stampSlots(CustomerSubscription $subscription, array $slots, bool $pushToCms = true): void
    {
        $now = now();
        $changes = [];

        foreach ($slots as $slot) {
            if (! in_array($slot, CustomerSubscription::LOGO_SLOTS, true)) {
                continue;
            }
            $changes[self::timestampColumn($slot)] = $now;
        }

        if ($changes === []) {
            return;
        }

        $subscription->fill($changes);
        $subscription->saveQuietly();

        if ($pushToCms && (int) $subscription->subscription_type_id === 1) {
            SyncLogosToCmsJob::dispatch($subscription->id, $slots);
        }
    }

    /**
     * Build absolute logo URLs + timestamps for CMS sync payload.
     *
     * @param  list<string>|null  $slots
     * @return array{logos: array<string, array{url: ?string, updated_at: ?string}>}
     */
    public static function buildCmsPayload(CustomerSubscription $subscription, ?array $slots = null): array
    {
        $slots ??= array_keys(self::SLOT_TO_CMS);
        $logos = [];

        foreach ($slots as $slot) {
            $cmsKey = self::SLOT_TO_CMS[$slot] ?? null;
            if (! $cmsKey) {
                continue;
            }

            $path = $subscription->getAttribute($slot);
            $updatedAt = $subscription->getAttribute(self::timestampColumn($slot));

            $logos[$cmsKey] = [
                'url' => filled($path) ? self::absolutePublicUrl((string) $path) : null,
                'updated_at' => $updatedAt ? Carbon::parse($updatedAt)->toIso8601String() : null,
            ];
        }

        return ['logos' => $logos, 'source' => 'superadmin'];
    }

    public static function absolutePublicUrl(string $path): string
    {
        $path = trim($path);
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $normalized = ltrim($path, '/');
        if (str_starts_with($normalized, 'storage/')) {
            return rtrim((string) config('app.url'), '/').'/'.$normalized;
        }

        return rtrim((string) config('app.url'), '/').'/storage/'.$normalized;
    }

    /**
     * Apply CMS-pushed logo files when CMS timestamps are newer.
     *
     * @param  array<string, UploadedFile|null>  $files  keyed by logo_1/2/3
     * @param  array<string, string|null>  $timestamps  keyed by logo_1/2/3 ISO strings
     * @return list<string> applied slots
     */
    public static function applyCmsUpload(CustomerSubscription $subscription, array $files, array $timestamps): array
    {
        $disk = Storage::disk('public');
        $applied = [];
        $changes = [];

        foreach ($files as $slot => $file) {
            if (! in_array($slot, array_keys(self::SLOT_TO_CMS), true)) {
                continue;
            }
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $remoteTs = isset($timestamps[$slot]) && filled($timestamps[$slot])
                ? Carbon::parse($timestamps[$slot])
                : now();
            $localTs = $subscription->getAttribute(self::timestampColumn($slot));
            $localCarbon = $localTs ? Carbon::parse($localTs) : null;

            if ($localCarbon && $remoteTs->lt($localCarbon)) {
                Log::info('Skipping CMS logo push for '.$slot.' - local is newer', [
                    'subscription_id' => $subscription->id,
                    'local' => $localCarbon->toIso8601String(),
                    'remote' => $remoteTs->toIso8601String(),
                ]);

                continue;
            }

            $oldPath = $subscription->getAttribute($slot);
            $newPath = $file->store('/', 'public');
            $changes[$slot] = $newPath;
            $changes[self::timestampColumn($slot)] = $remoteTs;
            $applied[] = $slot;

            if (filled($oldPath) && $oldPath !== $newPath) {
                $disk->delete($oldPath);
            }
        }

        if ($changes !== []) {
            $subscription->fill($changes);
            $subscription->saveQuietly();
        }

        return $applied;
    }
}
