<?php

namespace App\Support\UserFieldSync;

use App\Models\CustomerUser;
use App\Models\CustomerUserFieldValue;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Canonical wire shape for a batch of custom field values on one user.
 */
final class UserFieldValueSyncPayload
{
    /**
     * @param  list<array{name: string, value: ?string, updated_at: ?CarbonInterface}>  $values
     */
    public function __construct(
        public readonly ?int $superAdminUserId,
        public readonly array $values,
        public readonly ?int $superAdminCustomerId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $values = [];
        foreach ((array) ($data['values'] ?? []) as $row) {
            if (! is_array($row) || blank($row['name'] ?? null)) {
                continue;
            }

            $value = $row['value'] ?? null;
            if ($value !== null && ! is_string($value)) {
                $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            }

            $values[] = [
                'name' => (string) $row['name'],
                'value' => $value === '' ? null : $value,
                'updated_at' => isset($row['updated_at']) && filled($row['updated_at'])
                    ? Carbon::parse($row['updated_at'])
                    : null,
            ];
        }

        return new self(
            superAdminUserId: isset($data['super_admin_user_id'])
                ? (int) $data['super_admin_user_id']
                : null,
            values: $values,
            superAdminCustomerId: isset($data['super_admin_customer_id'])
                ? (int) $data['super_admin_customer_id']
                : null,
        );
    }

    /**
     * @param  Collection<int, CustomerUserFieldValue>  $rows
     */
    public static function fromUser(CustomerUser $user, Collection $rows): self
    {
        $values = $rows->map(function (CustomerUserFieldValue $row): array {
            $row->loadMissing('field');

            return [
                'name' => (string) ($row->field?->name ?? ''),
                'value' => $row->value,
                'updated_at' => $row->updated_at,
            ];
        })->filter(fn (array $row): bool => $row['name'] !== '')->values()->all();

        return new self(
            superAdminUserId: $user->id,
            values: $values,
            superAdminCustomerId: $user->customer_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'super_admin_user_id' => $this->superAdminUserId,
            'values' => array_map(function (array $row): array {
                return [
                    'name' => $row['name'],
                    'value' => $row['value'],
                    'updated_at' => $row['updated_at'] instanceof CarbonInterface
                        ? $row['updated_at']->toIso8601String()
                        : $row['updated_at'],
                ];
            }, $this->values),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toLmsArray(int $superAdminCustomerId): array
    {
        return array_merge($this->toArray(), [
            'super_admin_customer_id' => $superAdminCustomerId,
        ]);
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    public static function validationRules(string $prefix = 'user_field_values'): array
    {
        $key = $prefix === '' ? '' : $prefix.'.';

        return [
            $key.'super_admin_user_id' => ['required', 'integer', 'min:1'],
            $key.'values' => ['required', 'array', 'min:1'],
            $key.'values.*.name' => ['required', 'string', 'max:255'],
            $key.'values.*.value' => ['nullable'],
            $key.'values.*.updated_at' => ['nullable', 'date'],
            $key.'super_admin_customer_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
