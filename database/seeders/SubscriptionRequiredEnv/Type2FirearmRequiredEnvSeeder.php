<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

use Illuminate\Database\Seeder;

class Type2FirearmRequiredEnvSeeder extends Seeder
{
    public function run(): void
    {
        $p = EnvPlaceholder::PLH;

        SubscriptionRequiredEnvRecords::sync(2, [
            ['key' => 'APP_NAME', 'value' => 'Firearm Management System'],
            ['key' => 'APP_ENV', 'value' => 'production'],
            ['key' => 'APP_KEY', 'value' => 'base64:'.$p],
            ['key' => 'APP_DEBUG', 'value' => 'false'],
            ['key' => 'APP_URL', 'value' => 'http://fms.ekasilam.tech'],
            ['key' => 'APP_LOCALE', 'value' => 'en'],
            ['key' => 'APP_TIMEZONE', 'value' => 'Africa/Johannesburg'],
            ['key' => 'APP_FALLBACK_LOCALE', 'value' => 'en'],
            ['key' => 'APP_FAKER_LOCALE', 'value' => 'en_US'],
            ['key' => 'APP_MAINTENANCE_DRIVER', 'value' => 'file'],
            ['key' => 'PHP_CLI_SERVER_WORKERS', 'value' => '4'],
            ['key' => 'BCRYPT_ROUNDS', 'value' => '12'],
            ['key' => 'LOG_CHANNEL', 'value' => 'stack'],
            ['key' => 'LOG_STACK', 'value' => 'single'],
            ['key' => 'LOG_DEPRECATIONS_CHANNEL', 'value' => 'null'],
            ['key' => 'LOG_LEVEL', 'value' => 'debug'],
            ['key' => 'DB_CONNECTION', 'value' => 'mysql'],
            ['key' => 'DB_HOST', 'value' => '127.0.0.1'],
            ['key' => 'DB_PORT', 'value' => '3306'],
            ['key' => 'DB_DATABASE', 'value' => 'firearm_v4'],
            ['key' => 'DB_USERNAME', 'value' => 'forge'],
            ['key' => 'DB_PASSWORD', 'value' => $p],
            ['key' => 'SESSION_DRIVER', 'value' => 'database'],
            ['key' => 'SESSION_LIFETIME', 'value' => '120'],
            ['key' => 'SESSION_ENCRYPT', 'value' => 'false'],
            ['key' => 'SESSION_PATH', 'value' => '/'],
            ['key' => 'SESSION_DOMAIN', 'value' => 'null'],
            ['key' => 'BROADCAST_CONNECTION', 'value' => 'log'],
            ['key' => 'FILESYSTEM_DISK', 'value' => 'local'],
            ['key' => 'QUEUE_CONNECTION', 'value' => 'database'],
            ['key' => 'CACHE_STORE', 'value' => 'database'],
            ['key' => 'MEMCACHED_HOST', 'value' => '127.0.0.1'],
            ['key' => 'REDIS_CLIENT', 'value' => 'phpredis'],
            ['key' => 'REDIS_HOST', 'value' => '127.0.0.1'],
            ['key' => 'REDIS_PASSWORD', 'value' => ''],
            ['key' => 'REDIS_PORT', 'value' => '6379'],
            ['key' => 'MAIL_MAILER', 'value' => 'log'],
            ['key' => 'MAIL_SCHEME', 'value' => 'null'],
            ['key' => 'MAIL_HOST', 'value' => '127.0.0.1'],
            ['key' => 'MAIL_PORT', 'value' => '2525'],
            ['key' => 'MAIL_USERNAME', 'value' => 'null'],
            ['key' => 'MAIL_PASSWORD', 'value' => 'null'],
            ['key' => 'MAIL_FROM_ADDRESS', 'value' => 'hello@example.com'],
            ['key' => 'MAIL_FROM_NAME', 'value' => '${APP_NAME}'],
            ['key' => 'AWS_ACCESS_KEY_ID', 'value' => ''],
            ['key' => 'AWS_SECRET_ACCESS_KEY', 'value' => ''],
            ['key' => 'AWS_DEFAULT_REGION', 'value' => 'us-east-1'],
            ['key' => 'AWS_BUCKET', 'value' => ''],
            ['key' => 'AWS_USE_PATH_STYLE_ENDPOINT', 'value' => 'false'],
            ['key' => 'VITE_APP_NAME', 'value' => '${APP_NAME}'],
        ]);
    }
}
