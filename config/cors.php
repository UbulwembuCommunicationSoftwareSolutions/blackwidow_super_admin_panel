<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['app_manifest', 'customer-logo', 'customer_logo/*', 'customer_logos', 'api/*', 'sanctum/csrf-cookie', 'google-places-proxy'],

    'allowed_methods' => ['*'],

    /*
    | Wildcard origins (e.g. https://*.blackwidow.org.za) are converted to regex by
    | Fruitcake—you cannot use Laravel Str::is globs inside allowed_origins_patterns
    | (those are preg_match regexes).
    |
    | https://*.blackwidow.org.za covers demo.responder.blackwidow.org.za (any depth).
    */
    'allowed_origins' => [
        'https://*.blackwidow.org.za',
        'http://*.blackwidow.org.za',
        'https://*.heartbeatnetworks.com',
        'http://*.heartbeatnetworks.com',
        'https://*.bvigilant.co.za',
        'http://*.bvigilant.co.za',
        'https://*.aims.net.za',
        'http://*.aims.net.za',
        'https://*.aims.world',
        'http://*.aims.world',
        'https://*.siyaleader.org.za',
        'http://*.siyaleader.org.za',

        /*
        | Vue admin SPA (Reseller Console). Listed as exact origins so branded
        | production hosts are allowed even if a domain family is missing from
        | the wildcards above. The Forge preview is listed exactly because
        | on-forge.com is shared across all Forge customers — a
        | https://*.on-forge.com wildcard would open this API to every site on it.
        */
        'https://superadmin.aims.world',
        'https://superadmin.bvigilant.co.za',
        'https://superadmin.blackwidow.org.za',
        'https://superadmin.siyaleader.org.za',
        'https://superadmin.aims.net.za',
        'https://super_admin_frontend-eumaqzrf.on-forge.com',
    ],

    'allowed_origins_patterns' => [
        '#^https?://(localhost|127\.0\.0\.1)(:\d+)?\z#iu',
        '#^https?://[^/]*siyaleader\.#iu',
    ],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'Origin',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
