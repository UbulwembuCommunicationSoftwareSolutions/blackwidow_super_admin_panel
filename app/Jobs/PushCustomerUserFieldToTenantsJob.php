<?php

namespace App\Jobs;

use App\Models\CustomerUserField;
use App\Services\UserFieldSync\TenantUserFieldPusher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PushCustomerUserFieldToTenantsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $customerUserFieldId,
        public string $operation = 'upsert',
    ) {}

    public function handle(TenantUserFieldPusher $pusher): void
    {
        $query = CustomerUserField::query();
        if (in_array($this->operation, ['archive'], true)) {
            $query->withTrashed();
        }

        $field = $query->find($this->customerUserFieldId);
        if ($field === null) {
            Log::warning('User field push skipped: field no longer exists', [
                'customer_user_field_id' => $this->customerUserFieldId,
            ]);

            return;
        }

        $pusher->pushDefinition($field, $this->operation);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('User field push to tenants failed permanently', [
            'customer_user_field_id' => $this->customerUserFieldId,
            'operation' => $this->operation,
            'error' => $exception->getMessage(),
        ]);
    }
}
