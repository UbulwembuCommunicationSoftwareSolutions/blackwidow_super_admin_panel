<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use Illuminate\Support\Facades\Log;

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
     * Stamp logo slot timestamps without pushing to tenants.
     * Outbound push is handled by CustomerSubscription::queueBrandingPush / model hooks.
     *
     * @param  list<string>  $slots
     */
    public static function stampSlots(CustomerSubscription $subscription, array $slots, bool $pushToCms = false): void
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

        if ($pushToCms) {
            Log::debug('LogoSyncService::stampSlots pushToCms is deprecated; use PushBrandingToTenantsJob', [
                'subscription_id' => $subscription->id,
                'slots' => $slots,
            ]);
        }
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
}
