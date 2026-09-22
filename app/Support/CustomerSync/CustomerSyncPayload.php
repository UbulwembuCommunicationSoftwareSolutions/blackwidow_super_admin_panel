<?php

namespace App\Support\CustomerSync;

use App\Models\Customer;
use Illuminate\Support\Str;

final class CustomerSyncPayload
{
    public function __construct(
        public int $superAdminCustomerId,
        public ?string $uuid,
        public string $companyName,
        public string $slug,
        public int $maxUsers,
        public bool $isActive,
        public string $syncHash,
    ) {}

    public static function fromCustomer(Customer $customer): self
    {
        $payload = new self(
            superAdminCustomerId: $customer->id,
            uuid: $customer->uuid !== null ? (string) $customer->uuid : null,
            companyName: (string) $customer->company_name,
            slug: Str::slug((string) $customer->company_name),
            maxUsers: (int) ($customer->max_users ?? 1),
            isActive: true,
            syncHash: '',
        );

        $payload->syncHash = self::hashForWireShape($payload->toArray());

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            superAdminCustomerId: (int) ($data['super_admin_customer_id'] ?? 0),
            uuid: isset($data['uuid']) ? (string) $data['uuid'] : null,
            companyName: (string) ($data['company_name'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
            maxUsers: (int) ($data['max_users'] ?? 1),
            isActive: (bool) ($data['is_active'] ?? true),
            syncHash: (string) ($data['sync_hash'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'super_admin_customer_id' => $this->superAdminCustomerId,
            'uuid' => $this->uuid,
            'company_name' => $this->companyName,
            'slug' => $this->slug,
            'max_users' => $this->maxUsers,
            'is_active' => $this->isActive,
            'sync_hash' => $this->syncHash,
        ];
    }

    /**
     * @param  array<string, mixed>  $wire
     */
    public static function hashForWireShape(array $wire): string
    {
        unset($wire['sync_hash']);
        ksort($wire);

        return hash('sha256', (string) json_encode($wire));
    }
}
