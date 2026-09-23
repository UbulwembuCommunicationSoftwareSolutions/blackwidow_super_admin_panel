<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

use Illuminate\Database\Seeder;

class Type12LmsRequiredEnvSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionRequiredEnvRecords::sync(12, $this->rows());
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    private function rows(): array
    {
        $p = EnvPlaceholder::PLH;
        $pSentry = EnvPlaceholder::PLH_SENTRY;

        return [
            ['key' => 'APP_NAME', 'value' => 'BlackWidow LMS'],
            ['key' => 'APP_ENV', 'value' => 'local'],
            ['key' => 'APP_KEY', 'value' => 'base64:'.$p],
            ['key' => 'APP_DEBUG', 'value' => 'true'],
            ['key' => 'APP_TIMEZONE', 'value' => 'Africa/Johannesburg'],
            ['key' => 'APP_URL', 'value' => 'https://hub.lms.blackwidow.org.za'],
            ['key' => 'APP_LOCALE', 'value' => 'en'],
            ['key' => 'APP_FALLBACK_LOCALE', 'value' => 'en'],
            ['key' => 'APP_FAKER_LOCALE', 'value' => 'en_US'],
            ['key' => 'APP_MAINTENANCE_DRIVER', 'value' => 'file'],
            ['key' => 'BCRYPT_ROUNDS', 'value' => '12'],
            ['key' => 'LOG_CHANNEL', 'value' => 'stack'],
            ['key' => 'LOG_STACK', 'value' => 'single'],
            ['key' => 'LOG_DEPRECATIONS_CHANNEL', 'value' => 'null'],
            ['key' => 'LOG_LEVEL', 'value' => 'debug'],
            ['key' => 'DB_CONNECTION', 'value' => 'mysql'],
            ['key' => 'DB_HOST', 'value' => '127.0.0.1'],
            ['key' => 'DB_PORT', 'value' => '3306'],
            ['key' => 'DB_DATABASE', 'value' => 'blackwidow_lms'],
            ['key' => 'DB_USERNAME', 'value' => 'forge'],
            ['key' => 'DB_PASSWORD', 'value' => $p],
            ['key' => 'SESSION_DRIVER', 'value' => 'database'],
            ['key' => 'SESSION_LIFETIME', 'value' => '120'],
            ['key' => 'SESSION_ENCRYPT', 'value' => 'false'],
            ['key' => 'SESSION_PATH', 'value' => '/'],
            ['key' => 'SESSION_DOMAIN', 'value' => 'null'],
            ['key' => 'COOKIE_DOMAIN', 'value' => '.blackwidow.org.za'],
            ['key' => 'BROADCAST_CONNECTION', 'value' => 'log'],
            ['key' => 'FILESYSTEM_DISK', 'value' => 'local'],
            ['key' => 'QUEUE_CONNECTION', 'value' => 'redis'],
            ['key' => 'CACHE_STORE', 'value' => 'database'],
            ['key' => 'REDIS_HOST', 'value' => '127.0.0.1'],
            ['key' => 'REDIS_PASSWORD', 'value' => 'null'],
            ['key' => 'REDIS_PORT', 'value' => '6379'],
            ['key' => 'MAIL_MAILER', 'value' => 'smtp'],
            ['key' => 'MAIL_HOST', 'value' => 'mail.blackwidow.org.za'],
            ['key' => 'MAIL_PORT', 'value' => '25'],
            ['key' => 'MAIL_USERNAME', 'value' => 'admin@blackwidow.org.za'],
            ['key' => 'MAIL_PASSWORD', 'value' => $p],
            ['key' => 'MAIL_ENCRYPTION', 'value' => 'null'],
            ['key' => 'MAIL_FROM_ADDRESS', 'value' => 'admin@blackwidow.org.za'],
            ['key' => 'MAIL_FROM_NAME', 'value' => '${APP_NAME}'],
            ['key' => 'VITE_APP_NAME', 'value' => '${APP_NAME}'],
            ['key' => 'SENTRY_LARAVEL_DSN', 'value' => $pSentry],
            ['key' => 'SENTRY_TRACES_SAMPLE_RATE', 'value' => '1.0'],
            ['key' => 'SECURE_TOKEN', 'value' => 'token'],
            ['key' => 'SUPERADMIN_API_URL', 'value' => 'https://superadmin.blackwidow.org.za'],
            ['key' => 'SUPERADMIN_USER_SYNC_ENABLED', 'value' => 'true'],
        ];
    }
}
