<?php

return [
    'base_url' => env('LINKS_BASE_URL'),
    'android_package' => env('LINKS_ANDROID_PACKAGE'),
    'android_fingerprints' => array_values(array_filter(array_map('trim', explode(',', (string) env('LINKS_ANDROID_FINGERPRINTS', ''))))),
    'ios_app_id' => env('LINKS_IOS_APP_ID'),
    'android_store_url' => env('LINKS_ANDROID_STORE_URL'),
    'ios_store_url' => env('LINKS_IOS_STORE_URL'),
    'referral_claim_hours' => 24,
];
