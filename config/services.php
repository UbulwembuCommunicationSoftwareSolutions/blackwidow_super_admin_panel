<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'forge' => [
        'key' => env('FORGE_API_KEY'),
        // The only Forge organization this app is allowed to see/manage (https://forge.laravel.com/{slug}).
        'organization' => env('FORGE_ORGANIZATION_SLUG', 'richard-hall-zbo'),
    ],

    'github' => [
        'token' => env('GITHUB_TOKEN'),
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    ],

    'google' => [
        'places_api_key' => env('GOOGLE_PLACES_API_KEY'),
    ],

    /*
     * Bearer token the shared LMS uses when calling GET /api/v1/sync/customers and
     * that Super Admin uses when POSTing customer upserts to the LMS admin-api.
     * Must match the LMS app SECURE_TOKEN.
     */
    'lms' => [
        'sync_token' => env('LMS_SYNC_TOKEN'),
    ],

];
