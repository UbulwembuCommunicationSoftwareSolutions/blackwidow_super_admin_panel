<?php

declare(strict_types=1);

namespace App\Services\Reporting\Aims;

use Illuminate\Validation\ValidationException;

/**
 * AIMS report access as plain key/value pairs. The editor does not know these
 * keys; this class is the only place that maps them onto rpt_role_grant.
 */
final class AimsReportingSettings
{
    public const CLOCK_KEY = 'clock_offset_minutes';

    /** @var list<string> */
    private const SUFFIXES = [
        '_role_ids',
        '_can_dashboard',
        '_can_cases',
        '_can_audit',
        '_can_export',
        '_can_see_names',
        '_scope',
        '_note',
    ];

    /** @var list<string> */
    private const FLAGS = ['can_dashboard', 'can_cases', 'can_audit', 'can_export', 'can_see_names'];

    /** @var list<string> */
    private const SCOPES = ['all', 'org', 'participant', 'none'];

    /**
     * @param  list<array<string, mixed>>  $grants
     * @return list<array{key: string, value: string}>
     */
    public function pairs(array $grants, ?int $clockOffset): array
    {
        $pairs = [];

        foreach ($grants as $grant) {
            $key = (string) $grant['grant_key'];
            $pairs[] = ['key' => $key.'_role_ids', 'value' => $this->formatRoleIds($grant['role_ids'] ?? [])];
            $pairs[] = ['key' => $key.'_scope', 'value' => (string) $grant['scope']];
            foreach (self::FLAGS as $flag) {
                $pairs[] = ['key' => $key.'_'.$flag, 'value' => (string) (int) ($grant[$flag] ?? 0)];
            }
            $note = trim((string) ($grant['note'] ?? ''));
            if ($note !== '') {
                $pairs[] = ['key' => $key.'_note', 'value' => $note];
            }
        }

        if ($clockOffset !== null) {
            $pairs[] = ['key' => self::CLOCK_KEY, 'value' => (string) $clockOffset];
        }

        return $pairs;
    }

    /**
     * @param  list<array{key?: mixed, value?: mixed}>  $pairs
     * @return array{grants: list<array<string, mixed>>, clock: int|null}
     */
    public function document(array $pairs): array
    {
        if ($pairs === []) {
            throw ValidationException::withMessages([
                'settings' => 'Add at least one setting.',
            ]);
        }

        $seen = [];
        /** @var array<string, array<string, mixed>> $grants */
        $grants = [];
        $clock = null;

        foreach ($pairs as $index => $pair) {
            $key = trim((string) ($pair['key'] ?? ''));
            $value = trim((string) ($pair['value'] ?? ''));
            $field = 'settings.'.$index.'.key';

            if ($key === '' || ! preg_match('/^[a-z][a-z0-9_]{0,79}$/', $key)) {
                throw ValidationException::withMessages([
                    $field => 'Use a short key of lowercase letters, numbers and underscores.',
                ]);
            }
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    $field => 'Each key can only appear once.',
                ]);
            }
            $seen[$key] = true;

            if ($key === self::CLOCK_KEY) {
                if (! preg_match('/^-?\d+$/', $value) || (int) $value < -1440 || (int) $value > 1440) {
                    throw ValidationException::withMessages([
                        'settings.'.$index.'.value' => 'Clock offset is a whole number of minutes, from -1440 to 1440.',
                    ]);
                }
                $clock = (int) $value;

                continue;
            }

            $slot = $this->slot($key);
            if ($slot === null) {
                throw ValidationException::withMessages([
                    $field => 'Unknown setting. Role slots use keys such as investigator_role_ids.',
                ]);
            }

            [$grantKey, $fieldName] = $slot;
            $grants[$grantKey] ??= $this->blankGrant($grantKey);
            $grants[$grantKey][$fieldName] = $this->castField($fieldName, $value, $index);
            $grants[$grantKey]['_seen'][$fieldName] = true;
        }

        $documents = [];
        foreach ($grants as $grant) {
            $seenFields = $grant['_seen'];
            unset($grant['_seen']);
            if (! isset($seenFields['role_ids'], $seenFields['scope'])) {
                throw ValidationException::withMessages([
                    'settings' => $grant['grant_key'].' needs both '.$grant['grant_key'].'_role_ids and '.$grant['grant_key'].'_scope.',
                ]);
            }
            $documents[] = $grant;
        }

        return ['grants' => $documents, 'clock' => $clock];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function slot(string $key): ?array
    {
        foreach (self::SUFFIXES as $suffix) {
            if (str_ends_with($key, $suffix) && strlen($key) > strlen($suffix)) {
                $grantKey = substr($key, 0, -strlen($suffix));
                if (strlen($grantKey) > 50 || ! preg_match('/^[a-z][a-z0-9_]*$/', $grantKey)) {
                    return null;
                }

                return [$grantKey, ltrim($suffix, '_')];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function blankGrant(string $grantKey): array
    {
        return [
            'grant_key' => $grantKey,
            'role_ids' => [],
            'scope' => 'none',
            'can_dashboard' => 0,
            'can_cases' => 0,
            'can_audit' => 0,
            'can_export' => 0,
            'can_see_names' => 0,
            'note' => null,
            '_seen' => [],
        ];
    }

    private function castField(string $field, string $value, int $index): mixed
    {
        $errorKey = 'settings.'.$index.'.value';

        if ($field === 'role_ids') {
            return $this->parseRoleIds($value, $errorKey);
        }

        if ($field === 'scope') {
            if (! in_array($value, self::SCOPES, true)) {
                throw ValidationException::withMessages([
                    $errorKey => 'Scope must be all, org, participant or none.',
                ]);
            }

            return $value;
        }

        if ($field === 'note') {
            if (strlen($value) > 255) {
                throw ValidationException::withMessages([
                    $errorKey => 'A note can be at most 255 characters.',
                ]);
            }

            return $value === '' ? null : $value;
        }

        if (! in_array($value, ['0', '1'], true)) {
            throw ValidationException::withMessages([
                $errorKey => 'Use 0 or 1.',
            ]);
        }

        return (int) $value;
    }

    /**
     * @return list<int>
     */
    private function parseRoleIds(string $value, string $errorKey): array
    {
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            $tokens = is_array($decoded) ? $decoded : null;
        } else {
            $tokens = array_map(trim(...), explode(',', $value));
        }

        if (! is_array($tokens)) {
            throw ValidationException::withMessages([
                $errorKey => 'Role ids are a comma-separated list, or a JSON array.',
            ]);
        }

        $ids = [];
        foreach ($tokens as $token) {
            if ($token === '' || $token === null) {
                continue;
            }
            if (is_int($token)) {
                $id = $token;
            } elseif (is_string($token) && ctype_digit($token)) {
                $id = (int) $token;
            } else {
                throw ValidationException::withMessages([
                    $errorKey => 'Role ids are a comma-separated list, or a JSON array.',
                ]);
            }
            if ($id < 1) {
                throw ValidationException::withMessages([
                    $errorKey => 'Role ids are a comma-separated list, or a JSON array.',
                ]);
            }
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    private function formatRoleIds(mixed $stored): string
    {
        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            $stored = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($stored)) {
            return '';
        }

        $ids = [];
        foreach ($stored as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $ids[] = (string) (int) $id;
            }
        }

        return implode(', ', $ids);
    }
}
