<?php

/**
 * Access-control and abuse-prevention settings.
 *
 * Rate limits live here rather than as literals in the provider so they can be
 * tuned per environment (staging load tests need different values from
 * production) without a code change.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Rate limits (requests per minute)
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        // Authenticated API traffic, per user.
        'api' => (int) env('RATE_LIMIT_API', 60),

        // Webhooks are anonymous, so keyed by IP. Deliberately generous:
        // signature verification is the real control, and providers legitimately
        // burst. Phase 6 refines this.
        'webhooks' => (int) env('RATE_LIMIT_WEBHOOKS', 300),

        // Phase 3 uses these. Defined here so the limits are reviewable in one
        // place alongside the endpoints they protect.
        'login' => (int) env('RATE_LIMIT_LOGIN', 5),
        'otp_request' => (int) env('RATE_LIMIT_OTP_REQUEST', 3),
        'otp_verify' => (int) env('RATE_LIMIT_OTP_VERIFY', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development administrator
    |--------------------------------------------------------------------------
    |
    | Seeded only outside production, purely so a fresh clone is usable without
    | hand-crafting a user. The production guard in DatabaseSeeder is what stops
    | this becoming a shipped default credential.
    |
    */

    'dev_admin' => [
        'email' => env('DEV_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('DEV_ADMIN_PASSWORD', 'password'),
    ],

];
