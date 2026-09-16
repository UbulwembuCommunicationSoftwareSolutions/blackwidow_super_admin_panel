<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

use App\Models\SubscriptionType;
use Illuminate\Database\Seeder;

/**
 * Unlike the TypeN seeders, "Pre Case" has no fleet-wide fixed subscription_type_id yet
 * (it's a new module), so the row is resolved by name instead of a hardcoded literal.
 */
class PreCaseRequiredEnvSeeder extends Seeder
{
    public function run(): void
    {
        $subscriptionTypeId = SubscriptionType::query()->where('name', 'Pre Case')->value('id');
        if (! $subscriptionTypeId) {
            $this->command?->warn('Skipping PreCaseRequiredEnvSeeder: no "Pre Case" subscription type found.');

            return;
        }

        $p = EnvPlaceholder::PLH;

        $rows = array_merge(
            PhpModuleEnvTemplate::build(
                'PreCase',
                'https://precase.example.local',
                'blackwidow_precase',
                true
            ),
            [
                ['key' => 'REGISTRATION_ENABLED', 'value' => 'false'],
                ['key' => 'ICMS_INTEGRATION_ENABLED', 'value' => 'false'],
                ['key' => 'ICMS_BASE_URL', 'value' => $p],
                ['key' => 'ICMS_API_TOKEN', 'value' => $p],
                ['key' => 'ICMS_INTEGRATION_ENVIRONMENT', 'value' => 'test'],
                ['key' => 'ICMS_TIMEOUT', 'value' => '60'],
                ['key' => 'ICMS_CONNECT_TIMEOUT', 'value' => '10'],
                ['key' => 'ICMS_RETRY_ATTEMPTS', 'value' => '2'],
                ['key' => 'ICMS_VERIFY_SSL', 'value' => 'true'],
                ['key' => 'ICMS_QUEUE_CONNECTION', 'value' => 'database'],
                ['key' => 'ICMS_QUEUE', 'value' => 'integrations'],
                ['key' => 'ICMS_STATUS_POLL_MINUTES', 'value' => '15'],
            ]
        );

        SubscriptionRequiredEnvRecords::sync($subscriptionTypeId, $rows);
    }
}
