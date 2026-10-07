<?php

return [
    'email_enabled' => (bool) env('NOTIFICATIONS_EMAIL_ENABLED', false),
    'fcm_enabled' => (bool) env('NOTIFICATIONS_FCM_ENABLED', false),
    'fcm_project' => env('FCM_PROJECT_ID'),
    'fcm_credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
];
