<?php

return [

    /*
     * Master switch for pushing branding changes out to tenant apps.
     * Disabled in tests so factories don't fire HTTP calls.
     */
    'enabled' => env('BRANDING_SYNC_ENABLED', true),

    /*
     * Request timeout, in seconds, for outbound per-slot pushes.
     */
    'timeout' => env('BRANDING_SYNC_TIMEOUT', 30),

    /*
     * subscription_type_id of the tenant apps that speak the canonical branding
     * sync contract. Only these are pushed to. 1 is the console CMS.
     */
    'tenant_subscription_types' => [1],

];
