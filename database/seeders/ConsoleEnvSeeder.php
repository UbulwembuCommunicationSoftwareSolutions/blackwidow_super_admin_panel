<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConsoleEnvSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $settings = [
            ['key' => 'APP_DEBUG', 'value' => 'true', 'default_value' => 'true'],
            ['key' => 'APP_ENV', 'value' => 'local', 'default_value' => 'local'],
            ['key' => 'APP_FAKER_LOCALE', 'value' => 'en_US', 'default_value' => 'en_US'],
            ['key' => 'APP_FALLBACK_LOCALE', 'value' => 'en', 'default_value' => 'en'],
            ['key' => 'APP_KEY', 'value' => 'base64:BUjEkLzKWYG1kVKxvwgbAOgkrrBcDc+6y3xxp8PqWEY=', 'default_value' => 'base64:BUjEkLzKWYG1kVKxvwgbAOgkrrBcDc+6y3xxp8PqWEY='],
            ['key' => 'APP_LOCALE', 'value' => 'en', 'default_value' => 'en'],
            ['key' => 'APP_MAINTENANCE_DRIVER', 'value' => 'file', 'default_value' => 'file'],
            ['key' => 'APP_MAINTENANCE_STORE', 'value' => 'database', 'default_value' => 'database'],
            ['key' => 'APP_NAME', 'value' => 'blackwidowdemosystemtest', 'default_value' => 'blackwidowdemosystemtest'],
            ['key' => 'APP_TIMEZONE', 'value' => 'Africa/Johannesburg', 'default_value' => 'Africa/Johannesburg'],
            ['key' => 'APP_URL', 'value' => 'https://cmsdemo.blackwidow.org.za', 'default_value' => 'https://cmsdemo.blackwidow.org.za'],
            ['key' => 'AWS_ACCESS_KEY_ID', 'value' => '40bFpvNbQrziRBuZQJOf', 'default_value' => '40bFpvNbQrziRBuZQJOf'],
            ['key' => 'AWS_BUCKET', 'value' => 'blackwidowdemosystemtest', 'default_value' => 'blackwidowdemosystemtest'],
            ['key' => 'AWS_DEFAULT_REGION', 'value' => 'us-east-1', 'default_value' => 'us-east-1'],
            ['key' => 'AWS_ENDPOINT', 'value' => 'http://miniojhb.ncloud.africa:9000', 'default_value' => 'http://miniojhb.ncloud.africa:9000'],
            ['key' => 'AWS_SECRET_ACCESS_KEY', 'value' => 'UmvyA4rEDAmtdybdr6Y7dF9TQzNdjSc2xzmjLi5M', 'default_value' => 'UmvyA4rEDAmtdybdr6Y7dF9TQzNdjSc2xzmjLi5M'],
            ['key' => 'AWS_URL', 'value' => 'http://miniojhb.ncloud.africa:9000', 'default_value' => 'http://miniojhb.ncloud.africa:9000'],
            ['key' => 'AWS_USE_PATH_STYLE_ENDPOINT', 'value' => 'false', 'default_value' => 'false'],
            ['key' => 'BCRYPT_ROUNDS', 'value' => '12', 'default_value' => '12'],
            ['key' => 'BROADCAST_CONNECTION', 'value' => 'log', 'default_value' => 'log'],
            ['key' => 'CACHE_STORE', 'value' => 'file', 'default_value' => 'file'],
            ['key' => 'DB_CONNECTION', 'value' => 'mysql', 'default_value' => 'mysql'],
            ['key' => 'DB_DATABASE', 'value' => 'blackwidow_cms', 'default_value' => 'blackwidow_cms'],
            ['key' => 'DB_HOST', 'value' => '127.0.0.1', 'default_value' => '127.0.0.1'],
            ['key' => 'DB_PASSWORD', 'value' => 'bdPxcq80PV3sDS9C6652', 'default_value' => 'bdPxcq80PV3sDS9C6652'],
            ['key' => 'DB_PORT', 'value' => '3306', 'default_value' => '3306'],
            ['key' => 'DB_USERNAME', 'value' => 'forge', 'default_value' => 'forge'],
            ['key' => 'DRIVER_APP_NAME', 'value' => 'Blackwidow_Driver_APP', 'default_value' => 'Blackwidow_Driver_APP'],
            ['key' => 'DRIVER_APP_URL', 'value' => 'https://driver.blackwidow.org.za', 'default_value' => 'https://driver.blackwidow.org.za'],
            ['key' => 'ELASTICSEARCH_HOST', 'value' => '102.135.162.106', 'default_value' => '102.135.162.106'],
            ['key' => 'ELASTICSEARCH_INDEX', 'value' => 'blackwidow_cms_demo', 'default_value' => 'blackwidow_cms_demo'],
            ['key' => 'ELASTICSEARCH_PASSWORD', 'value' => 'YZn1gATvQ+C2G_C-Sv0a', 'default_value' => 'YZn1gATvQ+C2G_C-Sv0a'],
            ['key' => 'ELASTICSEARCH_SCHEME', 'value' => 'http', 'default_value' => 'http'],
            ['key' => 'ELASTICSEARCH_SSL_VERIFICATION', 'value' => 'NULL', 'default_value' => 'NULL'],
            ['key' => 'ELASTICSEARCH_USERNAME', 'value' => 'elastic', 'default_value' => 'elastic'],
            ['key' => 'FILESYSTEM_CLOUD', 'value' => 's3', 'default_value' => 's3'],
            ['key' => 'FILESYSTEM_DISK', 'value' => 'local', 'default_value' => 'local'],
            ['key' => 'FILESYSTEM_DRIVER', 'value' => 'local', 'default_value' => 'local'],
            ['key' => 'FIREBASE_CREDENTIALS', 'value' => 'firebase-credentials.json', 'default_value' => 'firebase-credentials.json'],
            ['key' => 'FIREBASE_PROJECT', 'value' => 'blackwidow-cms-demo', 'default_value' => 'blackwidow-cms-demo'],
            ['key' => 'LOG_CHANNEL', 'value' => 'stack', 'default_value' => 'stack'],
            ['key' => 'LOG_DEPRECATIONS_CHANNEL', 'value' => 'null', 'default_value' => 'null'],
            ['key' => 'LOG_LEVEL', 'value' => 'debug', 'default_value' => 'debug'],
            ['key' => 'LOG_STACK', 'value' => 'single', 'default_value' => 'single'],
            ['key' => 'MAIL_EHLO_DOMAIN', 'value' => 'blackwidow.org.za', 'default_value' => 'blackwidow.org.za'],
            ['key' => 'MAIL_ENCRYPTION', 'value' => 'null', 'default_value' => 'null'],
            ['key' => 'MAIL_FROM_ADDRESS', 'value' => 'admin@blackwidow.org.za', 'default_value' => 'admin@blackwidow.org.za'],
            ['key' => 'MAIL_FROM_NAME', 'value' => '${APP_NAME}', 'default_value' => '${APP_NAME}'],
            ['key' => 'MAIL_HOST', 'value' => 'mail.blackwidow.org.za', 'default_value' => 'mail.blackwidow.org.za'],
            ['key' => 'MAIL_MAILER', 'value' => 'smtp', 'default_value' => 'smtp'],
            ['key' => 'MAIL_PASSWORD', 'value' => 'ittxle4K00m', 'default_value' => 'ittxle4K00m'],
            ['key' => 'MAIL_PORT', 'value' => '25', 'default_value' => '25'],
            ['key' => 'MAIL_TRANSPORT', 'value' => 'smtp', 'default_value' => 'smtp'],
            ['key' => 'MAIL_URL', 'value' => 'mail.blackwidow.org.za', 'default_value' => 'mail.blackwidow.org.za'],
            ['key' => 'MAIL_USERNAME', 'value' => 'admin@blackwidow.org.za', 'default_value' => 'admin@blackwidow.org.za'],
            ['key' => 'MEDIA_DISK', 'value' => 'public', 'default_value' => 'public'],
        ];

        foreach ($settings as $setting) {
            DB::table('your_table_name')->updateOrInsert(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'default_value' => $setting['default_value']]
            );
        }
    }
}
