<?php

use App\Services\Auth\TwilioSms;

return [
    'default' => env('SMS_PROVIDER', 'disabled'),
    'providers' => [
        'twilio' => ['driver' => TwilioSms::class, 'account_sid' => env('TWILIO_ACCOUNT_SID'), 'token' => env('TWILIO_AUTH_TOKEN'), 'from' => env('TWILIO_FROM')],
    ],
];
