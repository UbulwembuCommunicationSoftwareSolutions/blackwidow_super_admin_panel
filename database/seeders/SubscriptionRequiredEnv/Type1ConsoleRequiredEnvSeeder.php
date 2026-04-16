<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

use Illuminate\Database\Seeder;

class Type1ConsoleRequiredEnvSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionRequiredEnvRecords::sync(1, $this->rows());
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    private function rows(): array
    {
        $p = EnvPlaceholder::PLH;
        $pSentry = EnvPlaceholder::PLH_SENTRY;

        return [
            ['key' => 'APP_DEBUG', 'value' => 'true'],
            ['key' => 'APP_ENV', 'value' => 'local'],
            ['key' => 'APP_FAKER_LOCALE', 'value' => 'en_US'],
            ['key' => 'APP_FALLBACK_LOCALE', 'value' => 'en'],
            ['key' => 'APP_KEY', 'value' => 'base64:'.$p],
            ['key' => 'APP_LOCALE', 'value' => 'en'],
            ['key' => 'APP_MAINTENANCE_DRIVER', 'value' => 'file'],
            ['key' => 'APP_MAINTENANCE_STORE', 'value' => 'database'],
            ['key' => 'APP_NAME', 'value' => 'BlackwidowProjects'],
            ['key' => 'APP_TIMEZONE', 'value' => 'Africa/Johannesburg'],
            ['key' => 'APP_URL', 'value' => 'http://projects.blackwidow.org.za'],
            ['key' => 'AWS_ACCESS_KEY_ID', 'value' => $p],
            ['key' => 'AWS_BUCKET', 'value' => '${MINIO_BUCKET}'],
            ['key' => 'AWS_DEFAULT_REGION', 'value' => 'us-east-1'],
            ['key' => 'AWS_ENDPOINT', 'value' => '${MINIO_ENDPOINT}'],
            ['key' => 'AWS_SECRET_ACCESS_KEY', 'value' => $p],
            ['key' => 'AWS_URL', 'value' => '${MINIO_URL}/${MINIO_BUCKET}'],
            ['key' => 'AWS_USE_PATH_STYLE_ENDPOINT', 'value' => 'false'],
            ['key' => 'BCRYPT_ROUNDS', 'value' => '12'],
            ['key' => 'BROADCAST_CONNECTION', 'value' => 'log'],
            ['key' => 'CACHE_STORE', 'value' => 'file'],
            ['key' => 'DB_CONNECTION', 'value' => 'mysql'],
            ['key' => 'DB_DATABASE', 'value' => 'projects_cms'],
            ['key' => 'DB_HOST', 'value' => '127.0.0.1'],
            ['key' => 'DB_PASSWORD', 'value' => $p],
            ['key' => 'DB_PORT', 'value' => '3306'],
            ['key' => 'DB_USERNAME', 'value' => 'forge'],
            ['key' => 'DRIVER_APP_NAME', 'value' => ''],
            ['key' => 'DRIVER_APP_URL', 'value' => 'http://projects.blackwidow.org.za'],
            ['key' => 'ELASTICSEARCH_HOST', 'value' => '127.0.0.1'],
            ['key' => 'ELASTICSEARCH_INDEX', 'value' => 'blackwidow_cms_projects'],
            ['key' => 'ELASTICSEARCH_PASSWORD', 'value' => $p],
            ['key' => 'ELASTICSEARCH_SCHEME', 'value' => 'http'],
            ['key' => 'ELASTICSEARCH_SSL_VERIFICATION', 'value' => 'NULL'],
            ['key' => 'ELASTICSEARCH_USERNAME', 'value' => 'elastic'],
            ['key' => 'FILESYSTEM_CLOUD', 'value' => 's3'],
            ['key' => 'FILESYSTEM_DISK', 'value' => 'local'],
            ['key' => 'FILESYSTEM_DRIVER', 'value' => 'local'],
            ['key' => 'FIREBASE_CREDENTIALS', 'value' => 'firebase-credentials.json'],
            ['key' => 'FIREBASE_PROJECT', 'value' => 'blackwidow-cms-demo'],
            ['key' => 'GOOGLE_MAPS_API_KEY', 'value' => $p],
            ['key' => 'LOG_CHANNEL', 'value' => 'stack'],
            ['key' => 'LOG_DEPRECATIONS_CHANNEL', 'value' => 'null'],
            ['key' => 'LOG_LEVEL', 'value' => 'debug'],
            ['key' => 'LOG_STACK', 'value' => 'single'],
            ['key' => 'MAIL_EHLO_DOMAIN', 'value' => 'blackwidow.org.za'],
            ['key' => 'MAIL_ENCRYPTION', 'value' => 'null'],
            ['key' => 'MAIL_FROM_ADDRESS', 'value' => 'admin@blackwidow.org.za'],
            ['key' => 'MAIL_FROM_NAME', 'value' => '${APP_NAME}'],
            ['key' => 'MAIL_HOST', 'value' => 'mail.blackwidow.org.za'],
            ['key' => 'MAIL_MAILER', 'value' => 'smtp'],
            ['key' => 'MAIL_PASSWORD', 'value' => $p],
            ['key' => 'MAIL_PORT', 'value' => '25'],
            ['key' => 'MAIL_TRANSPORT', 'value' => 'smtp'],
            ['key' => 'MAIL_URL', 'value' => 'mail.blackwidow.org.za'],
            ['key' => 'MAIL_USERNAME', 'value' => 'admin@blackwidow.org.za'],
            ['key' => 'MEDIA_DISK', 'value' => 'public'],
            ['key' => 'MINIO_ACCESS_KEY_ID', 'value' => $p],
            ['key' => 'MINIO_BUCKET', 'value' => '${APP_NAME}'],
            ['key' => 'MINIO_DEFAULT_REGION', 'value' => 'us-east-1'],
            ['key' => 'MINIO_ENDPOINT', 'value' => 'http://localhost:9000'],
            ['key' => 'MINIO_SECRET_ACCESS_KEY', 'value' => $p],
            ['key' => 'MINIO_URL', 'value' => 'http://localhost:9000'],
            ['key' => 'MINIO_USE_PATH_STYLE_ENDPOINT', 'value' => 'true'],
            ['key' => 'PUSHER_APP_CLUSTER', 'value' => 'mt1'],
            ['key' => 'PUSHER_APP_ID', 'value' => ''],
            ['key' => 'PUSHER_APP_KEY', 'value' => ''],
            ['key' => 'PUSHER_APP_SECRET', 'value' => ''],
            ['key' => 'PUSHER_HOST', 'value' => ''],
            ['key' => 'PUSHER_PORT', 'value' => '443'],
            ['key' => 'PUSHER_SCHEME', 'value' => 'https'],
            ['key' => 'QUEUE_CONNECTION', 'value' => 'redis'],
            ['key' => 'REDIS_DB', 'value' => '1'],
            ['key' => 'REDIS_HOST', 'value' => '127.0.0.1'],
            ['key' => 'REDIS_PASSWORD', 'value' => ''],
            ['key' => 'REDIS_PORT', 'value' => '6379'],
            ['key' => 'RESPONDER_APP_NAME', 'value' => ''],
            ['key' => 'RESPONDER_APP_URL', 'value' => 'http://projects.blackwidow.org.za'],
            ['key' => 'SCOUT_DRIVER', 'value' => 'elastic'],
            ['key' => 'SCOUT_ENABLED', 'value' => 'true'],
            ['key' => 'SCOUT_QUEUE', 'value' => 'true'],
            ['key' => 'SECURE_TOKEN', 'value' => 'token'],
            ['key' => 'SECURITY_APP_NAME', 'value' => ''],
            ['key' => 'SECURITY_APP_URL', 'value' => 'http://projects.blackwidow.org.za'],
            ['key' => 'SENTRY_DSN', 'value' => $pSentry],
            ['key' => 'SENTRY_LARAVEL_DSN', 'value' => $pSentry],
            ['key' => 'SENTRY_TRACES_SAMPLE_RATE', 'value' => '1.0'],
            ['key' => 'SESSION_DOMAIN', 'value' => 'null'],
            ['key' => 'SESSION_DRIVER', 'value' => 'database'],
            ['key' => 'SESSION_ENCRYPT', 'value' => 'false'],
            ['key' => 'SESSION_LIFETIME', 'value' => '120'],
            ['key' => 'SESSION_PATH', 'value' => '/'],
            ['key' => 'SURVEY_APP_NAME', 'value' => ''],
            ['key' => 'SURVEY_APP_URL', 'value' => 'http://projects.blackwidow.org.za'],
            ['key' => 'VITE_APP_NAME', 'value' => '${APP_NAME}'],
            ['key' => 'VITE_PUSHER_APP_CLUSTER', 'value' => '${PUSHER_APP_CLUSTER}'],
            ['key' => 'VITE_PUSHER_APP_KEY', 'value' => '${PUSHER_APP_KEY}'],
            ['key' => 'VITE_PUSHER_HOST', 'value' => '${PUSHER_HOST}'],
            ['key' => 'VITE_PUSHER_PORT', 'value' => '${PUSHER_PORT}'],
            ['key' => 'VITE_PUSHER_SCHEME', 'value' => '${PUSHER_SCHEME}'],
            ['key' => 'VITE_SENTRY_DSN_PUBLIC', 'value' => '${SENTRY_LARAVEL_DSN}'],
        ];
    }
}
