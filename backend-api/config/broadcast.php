<?php

return [
    'notifications' => [
        'queue' => env('BROADCAST_NOTIFICATION_QUEUE', 'broadcast'),
        'chunk_size' => (int) env('BROADCAST_NOTIFICATION_CHUNK_SIZE', 200),
    ],
    'whatsapp' => [
        'queue' => env('BROADCAST_WHATSAPP_QUEUE', 'broadcast-whatsapp'),
        'chunk_size' => (int) env('BROADCAST_WHATSAPP_CHUNK_SIZE', 50),
    ],
    'email' => [
        'queue' => env('BROADCAST_EMAIL_QUEUE', 'broadcast-email'),
        'chunk_size' => (int) env('BROADCAST_EMAIL_CHUNK_SIZE', 100),
    ],
];
