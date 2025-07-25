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

    'paths' => ['customer-logo','customer_logo/*','api/*', 'sanctum/csrf-cookie', 'google-places-proxy'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [
        '*localhost*',
        '*heartbeatnetworks.com*',
        '*blackwidow.org.za',
        '*bvigilant.co.za',
        '*siyaleader.*'
    ],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'Origin',
        'X-CSRF-TOKEN'
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
