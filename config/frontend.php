<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reseller Console SPA
    |--------------------------------------------------------------------------
    |
    | The Vue admin SPA is served from several branded hosts that all talk to
    | this one API. Emails that deep-link into the SPA (password resets) are
    | built against the origin the request came from, provided it is listed
    | here; otherwise they fall back to `url`.
    |
    */

    'url' => rtrim((string) env('FRONTEND_URL', 'https://superadmin.blackwidow.org.za'), '/'),

    'urls' => array_values(array_filter(array_map(
        fn ($u) => rtrim(trim((string) $u), '/'),
        explode(',', (string) env(
            'FRONTEND_URLS',
            'https://superadmin.aims.world,https://superadmin.bvigilant.co.za,https://superadmin.blackwidow.org.za,https://superadmin.siyaleader.org.za,https://superadmin.aims.net.za,https://super_admin_frontend-eumaqzrf.on-forge.com'
        ))
    ))),

    'password_reset_path' => '/reset-password',

];
