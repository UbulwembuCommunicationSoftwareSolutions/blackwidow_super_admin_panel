<?php

use App\Models\SubscriptionType;

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
     * sync contract. Only these are pushed to / may resync.
     * 1 = console CMS, 2 = Firearm.
     */
    'tenant_subscription_types' => [1, 2],

    /*
     * When true, customer-default branding changes are also POSTed to the shared LMS hub.
     */
    'lms_hub_enabled' => env('BRANDING_SYNC_LMS_HUB', true),

    /*
     * subscription_type_id of the shared LMS hub (same as customer_sync).
     */
    'lms_subscription_type_id' => (int) env('LMS_SUBSCRIPTION_TYPE_ID', SubscriptionType::LMS_TYPE_ID),

];
