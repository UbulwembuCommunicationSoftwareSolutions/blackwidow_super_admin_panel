<?php

declare(strict_types=1);

return [
    /*
    | IP of the machine running this API. When it matches the subscription's
    | Forge server, reports connect to 127.0.0.1. When it differs, they use
    | my_forge_servers.ip_address. Leave empty to always use 127.0.0.1
    | (the API is on the same server as the customer MySQL).
    */
    'api_server_ip' => env('REPORTING_API_SERVER_IP'),

    'query_timeout_ms' => (int) env('REPORTING_QUERY_TIMEOUT_MS', 30000),

    'export_row_limit' => (int) env('REPORTING_EXPORT_ROW_LIMIT', 5000),

    'schema_path' => resource_path('reporting/aims'),

    /*
    | MySQL account that creates the reporting database. Defaults to this
    | API's own DB_USERNAME / DB_PASSWORD, which on Forge is the forge user.
    | Reports still connect as the site user after that user is granted
    | access to the reporting database.
    */
    'install_username' => env('REPORTING_INSTALL_DB_USERNAME', env('DB_USERNAME', 'forge')),
    'install_password' => env('REPORTING_INSTALL_DB_PASSWORD', env('DB_PASSWORD', '')),

    /*
    | Origin of the Vue console. The CMS iframe loads /embed/dashboards/{id}
    | on this host, which draws the report without the Super Admin shell.
    */
    'frontend_url' => rtrim((string) env('REPORTING_FRONTEND_URL', 'https://super_admin_frontend-eumaqzrf.on-forge.com'), '/'),
];
