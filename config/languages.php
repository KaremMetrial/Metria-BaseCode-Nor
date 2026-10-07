<?php

/**
 * Supported application locales.
 *
 * This file is the single source of truth for locales. The keys of `supported`
 * are the only values accepted from clients; `config/translatable.php` mirrors
 * them for database content and a test asserts the two stay in sync.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Default locale
    |--------------------------------------------------------------------------
    |
    | Used when neither the request nor the authenticated user expresses a
    | supported preference.
    |
    */

    'default' => env('APP_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Fallback locale
    |--------------------------------------------------------------------------
    |
    | Used when a translation is missing for the active locale.
    |
    */

    'fallback' => env('APP_FALLBACK_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | `rtl` drives front-end direction. `native_name` is what locale pickers
    | display, so a user always recognises their own language.
    |
    */

    'supported' => [
        'en' => [
            'name' => 'English',
            'native_name' => 'English',
            'rtl' => false,
        ],
        'ar' => [
            'name' => 'Arabic',
            'native_name' => 'العربية',
            'rtl' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional X-Locale header
    |--------------------------------------------------------------------------
    |
    | Attack surface reduction: locale may also arrive in a dedicated header so
    | API clients are not forced to abuse Accept-Language.
    |
    */

    'header' => 'X-Locale',

];
