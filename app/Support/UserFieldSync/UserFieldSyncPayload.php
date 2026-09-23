<?php

namespace App\Support\UserFieldSync;

use App\Models\CustomerUserField;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Canonical wire shape for a custom user field definition.
 */
final class UserFieldSyncPayload
{
    /** @var list<string> */
    public const TYPES = ['text', 'textarea', 'select', 'checkbox', 'date'];

    /**
     * @param  array<int, string>|null  $options
     */
    public function __construct(
        public readonly ?int $superAdminUserFieldId,
        public readonly string $name,
        public readonly string $label,
        public readonly string $type,
        public readonly ?string $rules,
        public readonly ?array $options,
        public readonly int $sortOrder,
        public readonly bool $active,
        public readonly ?CarbonInterface $updatedAt,
        public readonly ?int $superAdminCustomerId = null,
        public readonly bool $deleted = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $options = $data['options'] ?? null;
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : null;
        }

        return new self(
            superAdminUserFieldId: isset($data['super_admin_user_field_id'])
                ? (int) $data['super_admin_user_field_id']
                : null,
            name: (string) ($data['name'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            type: (string) ($data['type'] ?? 'text'),
            rules: isset($data['rules']) && filled($data['rules']) ? (string) $data['rules'] : null,
            options: is_array($options) ? array_values(array_map('strval', $options)) : null,
            sortOrder: (int) ($data['sort_order'] ?? 0),
            active: filter_var($data['active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            updatedAt: isset($data['updated_at']) && filled($data['updated_at'])
                ? Carbon::parse($data['updated_at'])
                : null,
            superAdminCustomerId: isset($data['super_admin_customer_id'])
                ? (int) $data['super_admin_customer_id']
                : null,
            deleted: filter_var($data['deleted'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public static function fromModel(CustomerUserField $field): self
    {
        return new self(
            superAdminUserFieldId: $field->id,
            name: (string) $field->name,
            label: (string) $field->label,
            type: (string) $field->type,
            rules: $field->rules,
            options: is_array($field->options) ? $field->options : null,
            sortOrder: (int) $field->sort_order,
            active: (bool) $field->active,
            updatedAt: $field->updated_at,
            superAdminCustomerId: $field->customer_id,
            deleted: $field->trashed(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'super_admin_user_field_id' => $this->superAdminUserFieldId,
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'rules' => $this->rules,
            'options' => $this->options,
            'sort_order' => $this->sortOrder,
            'active' => $this->active,
            'updated_at' => $this->updatedAt?->toIso8601String(),
            'deleted' => $this->deleted,
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

    public function contentEquals(self $other): bool
    {
        return $this->name === $other->name
            && $this->label === $other->label
            && $this->type === $other->type
            && $this->rules === $other->rules
            && $this->options == $other->options
            && $this->sortOrder === $other->sortOrder
            && $this->active === $other->active
            && $this->deleted === $other->deleted;
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    public static function validationRules(string $prefix = 'user_field'): array
    {
        $key = $prefix === '' ? '' : $prefix.'.';

        return [
            $key.'super_admin_user_field_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            $key.'name' => ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9_]*$/'],
            $key.'label' => ['required', 'string', 'max:255'],
            $key.'type' => ['required', 'string', 'in:'.implode(',', self::TYPES)],
            $key.'rules' => ['nullable', 'string', 'max:1000'],
            $key.'options' => ['nullable', 'array'],
            $key.'options.*' => ['string', 'max:255'],
            $key.'sort_order' => ['sometimes', 'integer', 'min:0'],
            $key.'active' => ['sometimes', 'boolean'],
            $key.'updated_at' => ['nullable', 'date'],
            $key.'deleted' => ['sometimes', 'boolean'],
            $key.'super_admin_customer_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
