<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

/** Default .env rows for Laravel PHP add-on modules. */
final class PhpModuleEnvTemplate
{
    /**
     * @return list<array{key: string, value: string}>
     */
    public static function build(string $appName, string $appUrl, string $dbDatabase, bool $includeCmsUrl): array
    {
        $p = EnvPlaceholder::PLH;
        $pSentry = EnvPlaceholder::PLH_SENTRY;

        $rows = [
            ['key' => 'APP_NAME', 'value' => $appName],
            ['key' => 'APP_ENV', 'value' => 'local'],
            ['key' => 'APP_KEY', 'value' => 'base64:'.$p],
            ['key' => 'APP_DEBUG', 'value' => 'true'],
            ['key' => 'APP_TIMEZONE', 'value' => 'Africa/Johannesburg'],
            ['key' => 'APP_URL', 'value' => $appUrl],
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
            ['key' => 'DB_DATABASE', 'value' => $dbDatabase],
            ['key' => 'DB_USERNAME', 'value' => 'root'],
            ['key' => 'DB_PASSWORD', 'value' => $p],
            ['key' => 'SESSION_DRIVER', 'value' => 'file'],
            ['key' => 'SESSION_LIFETIME', 'value' => '120'],
            ['key' => 'SESSION_ENCRYPT', 'value' => 'false'],
            ['key' => 'SESSION_PATH', 'value' => '/'],
            ['key' => 'SESSION_DOMAIN', 'value' => 'null'],
            ['key' => 'BROADCAST_CONNECTION', 'value' => 'log'],
            ['key' => 'FILESYSTEM_DISK', 'value' => 'local'],
            ['key' => 'QUEUE_CONNECTION', 'value' => 'redis'],
            ['key' => 'CACHE_STORE', 'value' => 'database'],
            ['key' => 'CACHE_PREFIX', 'value' => ''],
            ['key' => 'MEMCACHED_HOST', 'value' => '127.0.0.1'],
            ['key' => 'REDIS_CLIENT', 'value' => 'phpredis'],
            ['key' => 'REDIS_HOST', 'value' => '127.0.0.1'],
            ['key' => 'REDIS_PASSWORD', 'value' => 'null'],
            ['key' => 'REDIS_PORT', 'value' => '6379'],
            ['key' => 'GOOGLE_MAPS_API_KEY', 'value' => $p],
            ['key' => 'MAIL_MAILER', 'value' => 'smtp'],
            ['key' => 'MAIL_HOST', 'value' => 'mail.blackwidow.org.za'],
            ['key' => 'MAIL_PORT', 'value' => '25'],
            ['key' => 'MAIL_USERNAME', 'value' => 'admin@blackwidow.org.za'],
            ['key' => 'MAIL_PASSWORD', 'value' => $p],
            ['key' => 'MAIL_ENCRYPTION', 'value' => 'null'],
            ['key' => 'MAIL_FROM_ADDRESS', 'value' => 'admin@blackwidow.org.za'],
            ['key' => 'MAIL_FROM_NAME', 'value' => '${APP_NAME}'],
            ['key' => 'AWS_ACCESS_KEY_ID', 'value' => ''],
            ['key' => 'AWS_SECRET_ACCESS_KEY', 'value' => ''],
            ['key' => 'AWS_DEFAULT_REGION', 'value' => 'us-east-1'],
            ['key' => 'AWS_BUCKET', 'value' => ''],
            ['key' => 'AWS_USE_PATH_STYLE_ENDPOINT', 'value' => 'false'],
            ['key' => 'VITE_APP_NAME', 'value' => '${APP_NAME}'],
            ['key' => 'MAIL_TRANSPORT', 'value' => 'smtp'],
            ['key' => 'MAIL_URL', 'value' => 'mail.blackwidow.org.za'],
            ['key' => 'MAIL_EHLO_DOMAIN', 'value' => 'blackwidow.org.za'],
            ['key' => 'SENTRY_LARAVEL_DSN', 'value' => $pSentry],
            ['key' => 'SENTRY_TRACES_SAMPLE_RATE', 'value' => '1.0'],
            ['key' => 'SECURE_TOKEN', 'value' => 'token'],
        ];

        if ($includeCmsUrl) {
            $rows[] = ['key' => 'CMS_URL', 'value' => 'https://cmsdemo.blackwidow.org.za'];
        }

        return $rows;
    }
}
