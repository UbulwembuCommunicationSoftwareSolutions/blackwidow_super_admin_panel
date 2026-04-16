<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

/** Default VUE_APP_* rows for HTML/Vue PWA apps. */
final class VuePwaEnvTemplate
{
    /**
     * @return list<array{key: string, value: string}>
     */
    public static function build(string $vueAppName): array
    {
        $p = EnvPlaceholder::PLH;

        return [
            ['key' => 'VUE_APP_API_BASE_URL', 'value' => 'https://cmsdemo.blackwidow.org.za'],
            ['key' => 'VUE_APP_apiKey', 'value' => $p],
            ['key' => 'VUE_APP_appId', 'value' => $p],
            ['key' => 'VUE_APP_authDomain', 'value' => 'blackwidow-cms-demo.firebaseapp.com'],
            ['key' => 'VUE_APP_logo_url', 'value' => 'https://www.example.org/logo.png'],
            ['key' => 'VUE_APP_measurementId', 'value' => $p],
            ['key' => 'VUE_APP_messagingSenderId', 'value' => $p],
            ['key' => 'VUE_APP_NAME', 'value' => $vueAppName],
            ['key' => 'VUE_APP_projectId', 'value' => 'blackwidow-cms-demo'],
            ['key' => 'VUE_APP_PUBLIC_DIR', 'value' => 'generic_public/img'],
            ['key' => 'VUE_APP_storageBucket', 'value' => 'blackwidow-cms-demo.firebasestorage.app'],
            ['key' => 'VUE_APP_TIMESHEET_URL', 'value' => 'https://example-timesheets.example.org/'],
            ['key' => 'VUE_APP_vapid_Key', 'value' => $p],
            ['key' => 'SECURE_TOKEN', 'value' => 'token'],
        ];
    }
}
