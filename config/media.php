<?php

return [
    'disk' => env('MEDIA_DISK', 'local'),
    'temporary_hours' => 24,
    'max_pending' => 20,
    'quota_bytes' => 200 * 1024 * 1024,
    'uploads_per_minute' => 10,
    'clamav_binary' => env('MEDIA_CLAMAV_BINARY'),
    'scan_timeout' => 30,
];
