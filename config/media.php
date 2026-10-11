<?php

return [
    'uploads' => [
        'avatar' => ['max_size_kb' => 2048, 'extensions' => ['jpg', 'jpeg', 'png', 'webp'], 'max_width' => 2048, 'max_height' => 2048],
        'support' => ['max_size_kb' => 10240, 'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 'max_width' => 4096, 'max_height' => 4096],
        'page' => ['max_size_kb' => 5120, 'extensions' => ['jpg', 'jpeg', 'png', 'webp'], 'max_width' => 4096, 'max_height' => 4096],
    ],
    'disk' => env('MEDIA_DISK', 'local'),
    'temporary_hours' => 24,
    'max_pending' => 20,
    'quota_bytes' => 200 * 1024 * 1024,
    'uploads_per_minute' => 10,
    'clamav_binary' => env('MEDIA_CLAMAV_BINARY'),
    'scan_timeout' => 30,
];
