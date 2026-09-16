<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\TemplateEnvVariables;

/**
 * Database-only env drift check (template keys vs EnvVariables rows vs subscription env blob).
 * Does not call Forge — safe when FORGE_API_KEY is unset.
 */
class EnvComparisonService
{
    /**
     * @return array{
     *     subscription_id: int,
     *     missing_keys: list<string>,
     *     extra_keys: list<string>,
     *     value_mismatches: list<array{key: string, matches: bool, configured?: string|null, blob?: string|null}>,
     *     template_key_count: int,
     *     configured_key_count: int,
     *     blob_key_count: int,
     *     blob_present: bool
     * }
     */
    public function compare(CustomerSubscription $subscription, bool $includeValues = false): array
    {
        $templateKeys = TemplateEnvVariables::query()
            ->where('subscription_type_id', $subscription->subscription_type_id)
            ->orderBy('key')
            ->pluck('key')
            ->all();

        $configured = EnvVariables::query()
            ->where('customer_subscription_id', $subscription->id)
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (EnvVariables $row) => [$row->key => $row->value])
            ->all();

        $blobPresent = filled($subscription->env);
        $blob = $blobPresent ? $this->parseEnvContent((string) $subscription->env) : [];

        $configuredKeys = array_keys($configured);
        $blobKeys = array_keys($blob);

        $missingKeys = array_values(array_diff($templateKeys, $configuredKeys));
        $extraKeys = array_values(array_diff($configuredKeys, $templateKeys));

        $valueMismatches = [];
        foreach ($configured as $key => $configuredValue) {
            if (! array_key_exists($key, $blob)) {
                $mismatch = [
                    'key' => $key,
                    'matches' => false,
                    'reason' => 'missing_from_blob',
                ];
                if ($includeValues) {
                    $mismatch['configured'] = $configuredValue;
                    $mismatch['blob'] = null;
                }
                $valueMismatches[] = $mismatch;

                continue;
            }

            $blobValue = $blob[$key];
            $matches = (string) $configuredValue === (string) $blobValue;
            if (! $matches) {
                $mismatch = [
                    'key' => $key,
                    'matches' => false,
                    'reason' => 'value_differs',
                ];
                if ($includeValues) {
                    $mismatch['configured'] = $configuredValue;
                    $mismatch['blob'] = $blobValue;
                }
                $valueMismatches[] = $mismatch;
            }
        }

        return [
            'subscription_id' => $subscription->id,
            'missing_keys' => $missingKeys,
            'extra_keys' => $extraKeys,
            'value_mismatches' => $valueMismatches,
            'template_key_count' => count($templateKeys),
            'configured_key_count' => count($configuredKeys),
            'blob_key_count' => count($blobKeys),
            'blob_present' => $blobPresent,
        ];
    }

    /**
     * Parse .env-style content or a JSON object string into key => value.
     *
     * @return array<string, string|null>
     */
    public function parseEnvContent(string $content): array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return [];
        }

        if (str_starts_with($trimmed, '{')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $out = [];
                foreach ($decoded as $key => $value) {
                    if (is_string($key)) {
                        $out[$key] = is_scalar($value) || $value === null
                            ? ($value === null ? null : (string) $value)
                            : json_encode($value);
                    }
                }

                return $out;
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $env = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '') {
                continue;
            }

            if (preg_match('/^"(.*)"$/s', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match("/^'(.*)'$/s", $value, $matches)) {
                $value = $matches[1];
            }

            $env[$key] = $value;
        }

        return $env;
    }
}
