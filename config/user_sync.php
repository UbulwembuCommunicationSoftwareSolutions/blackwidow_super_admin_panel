<?php

return [

    /*
     * Master switch for pushing customer user changes out to tenant apps.
     * Disabled in tests so factories don't fire HTTP calls.
     */
    'enabled' => env('USER_SYNC_ENABLED', true),

    /*
     * How long after a sync a record is left alone before another push is made.
     * Guards against echo storms when both sides write in quick succession.
     */
    'cooldown_seconds' => env('USER_SYNC_COOLDOWN_SECONDS', 30),

    /*
     * Request timeout, in seconds, for outbound per-record pushes.
     */
    'timeout' => env('USER_SYNC_TIMEOUT', 15),

    /*
     * subscription_type_id of the tenant apps that speak the canonical sync
     * contract. Only these are pushed to; add an app here once it exposes
     * /admin-api/v1/sync/users. 1 is the console CMS.
     */
    'tenant_subscription_types' => [1, 12],

];
