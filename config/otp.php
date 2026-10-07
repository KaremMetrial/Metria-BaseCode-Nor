<?php

return [
    'ttl_seconds' => (int) env('OTP_TTL_SECONDS', 300),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'cooldown_seconds' => (int) env('OTP_COOLDOWN_SECONDS', 60),
    'hourly_limit' => (int) env('OTP_HOURLY_LIMIT', 6),
    'mobile_only' => (bool) env('OTP_MOBILE_ONLY', true),
    'token_minutes' => (int) env('AUTH_TOKEN_MINUTES', 1440),
];
