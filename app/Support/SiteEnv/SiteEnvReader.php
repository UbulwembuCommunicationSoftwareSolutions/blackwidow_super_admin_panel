<?php

namespace App\Support\SiteEnv;

use App\Helpers\ForgeApi;
use App\Models\CustomerSubscription;

/**
 * Reads the .env a site is really running with, from Forge. Unlike
 * ForgeService::getSiteEnvironment() it never logs the file, which holds
 * every secret the site has.
 */
class SiteEnvReader
{
    public function read(CustomerSubscription $subscription): string
    {
        $forge = new ForgeApi;
        $subscription = $forge->assertForgeSiteReady($subscription);

        $response = $forge->forge->siteEnvironment(
            $forge->organizationSlugForServer((int) $subscription->server_id),
            $subscription->server_id,
            $subscription->forge_site_id,
        );

        if (is_array($response)) {
            return (string) ($response['content'] ?? '');
        }

        return (string) $response;
    }

    /**
     * Key => value for a .env file. Double-quoted values are unescaped the
     * way ForgeApi::formatEnvValue() escapes them, so a value survives being
     * pulled and pushed again.
     *
     * @return array<string, string>
     */
    public static function parse(string $content): array
    {
        $env = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $key = preg_replace('/^export\s+/', '', $key) ?? $key;

            if ($key === '') {
                continue;
            }

            if (preg_match('/^"(.*)"$/s', $value, $matches)) {
                $value = str_replace(['\\"', '\\\\'], ['"', '\\'], $matches[1]);
            } elseif (preg_match("/^'(.*)'$/s", $value, $matches)) {
                $value = $matches[1];
            }

            $env[$key] = $value;
        }

        return $env;
    }
}
