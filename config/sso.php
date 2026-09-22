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

    /*
    | Portal frontends the handoff may return the user to. A front proxy sets the
    | X-Sso-Portal header (per host); it is honoured only when listed here, so the
    | header can never redirect the one-time SSO code to an arbitrary site.
    */
    'customer_portal_urls' => array_values(array_filter(array_map(
        fn ($u) => rtrim(trim((string) $u), '/'),
        explode(',', (string) env('CUSTOMER_PORTAL_URLS', 'https://superadmin.blackwidow.org.za,https://superadmin.aims.net.za'))
    ))),

];
