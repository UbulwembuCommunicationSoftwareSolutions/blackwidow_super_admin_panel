<?php

use App\Models\SubscriptionType;

return [

    /*
     * Master switch for pushing custom user field changes out to tenant apps.
     * Disabled in tests so factories don't fire HTTP calls.
     */
    'enabled' => env('USER_FIELD_SYNC_ENABLED', true),

    /*
     * Request timeout, in seconds, for outbound pushes.
     */
    'timeout' => env('USER_FIELD_SYNC_TIMEOUT', 30),

    /*
     * subscription_type_id of the tenant apps that speak the canonical
     * user-field sync contract. 1 = console CMS, 2 = Firearm.
     */
    'tenant_subscription_types' => [1, 2],

    /*
     * When true, field definition/value changes are also POSTed to the shared LMS hub.
     */
    'lms_hub_enabled' => env('USER_FIELD_SYNC_LMS_HUB', true),

    /*
     * subscription_type_id of the shared LMS hub.
     */
    'lms_subscription_type_id' => (int) env('LMS_SUBSCRIPTION_TYPE_ID', SubscriptionType::LMS_TYPE_ID),

];
