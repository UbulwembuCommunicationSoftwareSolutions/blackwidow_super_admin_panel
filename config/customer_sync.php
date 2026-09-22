<?php

use App\Models\SubscriptionType;

return [

    /*
     * Master switch for pushing customer directory rows out to the shared LMS tenant.
     */
    'enabled' => env('CUSTOMER_SYNC_ENABLED', true),

    /*
     * Request timeout, in seconds, for outbound customer pushes.
     */
    'timeout' => env('CUSTOMER_SYNC_TIMEOUT', 30),

    /*
     * subscription_type_id of the shared LMS hub that consumes GET /api/v1/sync/customers.
     */
    'lms_subscription_type_id' => (int) env('LMS_SUBSCRIPTION_TYPE_ID', SubscriptionType::LMS_TYPE_ID),

];
