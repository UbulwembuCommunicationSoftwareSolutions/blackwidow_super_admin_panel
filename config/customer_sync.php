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
     * Optional legacy target: a type-12 subscription URL is still pushed to when
     * LMS_HUB_URL is empty. The shared hub itself is services.lms.hub_url.
     */
    'lms_subscription_type_id' => (int) env('LMS_SUBSCRIPTION_TYPE_ID', SubscriptionType::LMS_TYPE_ID),

];
