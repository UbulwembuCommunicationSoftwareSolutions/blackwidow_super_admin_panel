<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

use App\Models\SubscriptionType;
use Illuminate\Database\Seeder;

/**
 * "Pre Case" is not a fixed subscription_type_id in every database (the fleet uses 13;
 * some local copies use another id), so the row is resolved by name.
 *
 * The module already pulls users from Super Admin (`superadmin:sync-users`). The env
 * this seeder creates turns that sync on and names `precase_access` as the flag that
 * decides who is active. `SUPERADMIN_APP_URL` starts blank; site creation writes the
 * subscription URL into it so user-import can find the site.
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
                // Overrides of the shared PHP module defaults (later rows win in sync()).
                ['key' => 'APP_ENV', 'value' => 'production'],
                ['key' => 'APP_DEBUG', 'value' => 'false'],
                ['key' => 'SESSION_DRIVER', 'value' => 'database'],
                ['key' => 'QUEUE_CONNECTION', 'value' => 'database'],
                ['key' => 'MAIL_SCHEME', 'value' => 'null'],
                ['key' => 'LOCAL_DEVELOPMENT', 'value' => 'false'],
                ['key' => 'GOOGLE_PLACES_API_KEY', 'value' => $p],
                ['key' => 'GOOGLE_MAPS_BROWSER_API_KEY', 'value' => $p],
                ['key' => 'SUPERADMIN_SYNC_ENABLED', 'value' => 'true'],
                ['key' => 'SUPERADMIN_API_URL', 'value' => 'https://superadmin.blackwidow.org.za'],
                ['key' => 'SUPERADMIN_APP_URL', 'value' => ''],
                ['key' => 'SUPERADMIN_ACCESS_FLAG', 'value' => 'precase_access'],
                ['key' => 'SUPERADMIN_SYSTEM_ADMIN_ROLE', 'value' => 'Super Admin'],
                ['key' => 'SUPERADMIN_SYNC_INTERVAL', 'value' => '15'],
                ['key' => 'SUPERADMIN_TIMEOUT', 'value' => '60'],
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
