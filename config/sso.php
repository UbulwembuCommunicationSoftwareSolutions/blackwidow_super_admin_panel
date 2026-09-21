<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shared customer SSO cookie
    |--------------------------------------------------------------------------
    |
    | Parent-domain cookie carrying a SuperAdmin Sanctum token. Tenant apps
    | (CMS, Firearm) set it. This app reads it to open the customer portal.
    |
    */

    'cookie' => 'external_token',

    'customer_portal_url' => env('CUSTOMER_PORTAL_URL', 'https://super_admin_frontend-eumaqzrf.on-forge.com'),

];
