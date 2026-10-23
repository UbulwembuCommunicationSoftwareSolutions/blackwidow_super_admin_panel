<?php

namespace App\Support\SiteEnv;

use App\Models\CustomerSubscription;
use App\Models\EnvVariables;

/**
 * Brings Super Admin's env variable rows in line with the .env a site is really running.
 *
 * Without `$keepUnpushed` the site always wins. With it, a row edited in Super Admin after the
 * subscription was last in sync (env_synced_at) is an edit that has not reached the site yet, so it is
 * left alone and the next push sends it. A subscription that was never in sync only gets new keys.
 */
class SiteEnvPuller
{
    /** Never stored: it would hand every tenant row the Forge account. */
    public const IGNORED_KEYS = ['FORGE_API_KEY'];

    /**
     * @return array{add: list<string>, change: list<string>, kept: list<string>, pending: list<string>, only_here: list<string>, skipped: bool}
     */
    public function pull(CustomerSubscription $subscription, string $content, bool $apply, bool $keepUnpushed = false): array
    {
        $result = ['add' => [], 'change' => [], 'kept' => [], 'pending' => [], 'only_here' => [], 'skipped' => false];

        $site = array_diff_key(SiteEnvReader::parse($content), array_flip(self::IGNORED_KEYS));

        if ($site === []) {
            $result['skipped'] = true;

            return $result;
        }

        $existing = EnvVariables::query()
            ->where('customer_subscription_id', $subscription->id)
            ->get()
            ->keyBy('key');

        $syncedAt = $subscription->env_synced_at;

        foreach ($site as $key => $value) {
            $row = $existing->get($key);

            if ($row === null) {
                $result['add'][] = $key;

                if ($apply) {
                    $this->write(new EnvVariables(['customer_subscription_id' => $subscription->id, 'key' => $key]), $value, $syncedAt);
                }

                continue;
            }

            if ((string) $row->value === $value) {
                continue;
            }

            if ($value === '') {
                $result['kept'][] = $key;

                continue;
            }

            if ($keepUnpushed && ($syncedAt === null || $row->updated_at === null || $row->updated_at->gt($syncedAt))) {
                $result['pending'][] = $key;

                continue;
            }

            $result['change'][] = $key;

            if ($apply) {
                $this->write($row, $value, $syncedAt);
            }
        }

        $result['only_here'] = $existing->keys()->diff(array_keys($site))->values()->all();

        if ($apply) {
            $subscription->forceFill(['env' => $content]);

            // Every row matches the site again unless an unpushed edit is waiting.
            if ($result['pending'] === []) {
                $subscription->forceFill(['env_synced_at' => now()]);
            }

            $subscription->saveQuietly();
        }

        return $result;
    }

    /**
     * Saved with the timestamps it already had, so a pulled value does not look like an unpushed edit.
     */
    private function write(EnvVariables $row, string $value, mixed $syncedAt): void
    {
        $row->timestamps = false;
        $row->value = $value;

        if (! $row->exists) {
            $row->created_at = $syncedAt ?? now();
            $row->updated_at = $syncedAt ?? now();
        }

        $row->save();
    }
}
