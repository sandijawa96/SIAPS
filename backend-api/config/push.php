<?php

return [
    'enabled' => filter_var(env('PUSH_ENABLED', false), FILTER_VALIDATE_BOOL),
    'provider' => env('PUSH_PROVIDER', 'fcm'),
    'firebase' => [
        'endpoint' => env('FCM_ENDPOINT', ''),
        'android_channel_id' => env('FCM_ANDROID_CHANNEL_ID', 'siaps_notifications'),
        'service_account_path' => env('FCM_SERVICE_ACCOUNT_JSON', ''),
        'api_key' => env('FIREBASE_API_KEY', ''),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN', ''),
        'project_id' => env('FIREBASE_PROJECT_ID', ''),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET', ''),
        'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID', ''),
        'app_id' => env('FIREBASE_APP_ID', ''),
        'measurement_id' => env('FIREBASE_MEASUREMENT_ID', ''),
        'vapid_key' => env('FIREBASE_VAPID_KEY', ''),
    ],
];
