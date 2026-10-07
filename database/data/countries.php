<?php

/**
 * The countries the product actually operates in.
 *
 * This file is the *business* decision, not the data set. The full list of
 * countries -- 245 of them -- is generated at seed time from the numbering-plan
 * authority (libphonenumber) and the ICU-derived display names in
 * `giggsey/locale`, so adding a market is one line here plus a PATCH to flip
 * `is_active`, never a data-entry exercise.
 *
 * What the generated data cannot supply is currency and default timezone:
 * numbering plans do not know either. Those are declared here, and only for the
 * markets we are turning on. An unlisted country is seeded inactive with a null
 * currency, which is the honest state -- we have not decided to operate there
 * yet, and inventing a currency for it would be worse than leaving it blank.
 *
 * Array order is display order (`sort_order`), so the primary markets come first
 * in every country picker. Everything else sorts after them, alphabetically.
 */
return [

    // Primary market first: the whole phone/OTP flow is built and tested against
    // Egypt's numbering plan.
    'EG' => ['currency' => 'EGP', 'timezone' => 'Africa/Cairo'],
    'SA' => ['currency' => 'SAR', 'timezone' => 'Asia/Riyadh'],
    'AE' => ['currency' => 'AED', 'timezone' => 'Asia/Dubai'],
    'KW' => ['currency' => 'KWD', 'timezone' => 'Asia/Kuwait'],
    'QA' => ['currency' => 'QAR', 'timezone' => 'Asia/Qatar'],
    'BH' => ['currency' => 'BHD', 'timezone' => 'Asia/Bahrain'],
    'OM' => ['currency' => 'OMR', 'timezone' => 'Asia/Muscat'],
    'JO' => ['currency' => 'JOD', 'timezone' => 'Asia/Amman'],
    'LB' => ['currency' => 'LBP', 'timezone' => 'Asia/Beirut'],
    'IQ' => ['currency' => 'IQD', 'timezone' => 'Asia/Baghdad'],
    'PS' => ['currency' => 'ILS', 'timezone' => 'Asia/Gaza'],
    'MA' => ['currency' => 'MAD', 'timezone' => 'Africa/Casablanca'],
    'DZ' => ['currency' => 'DZD', 'timezone' => 'Africa/Algiers'],
    'TN' => ['currency' => 'TND', 'timezone' => 'Africa/Tunis'],
    'LY' => ['currency' => 'LYD', 'timezone' => 'Africa/Tripoli'],
    'SD' => ['currency' => 'SDG', 'timezone' => 'Africa/Khartoum'],
    'YE' => ['currency' => 'YER', 'timezone' => 'Asia/Aden'],
    'TR' => ['currency' => 'TRY', 'timezone' => 'Europe/Istanbul'],

];
