<?php
return [
 'default' => env('SMS_PROVIDER', 'disabled'),
 'providers' => [
  'twilio' => ['driver' => App\Services\Auth\TwilioSms::class, 'account_sid' => env('TWILIO_ACCOUNT_SID'), 'token' => env('TWILIO_AUTH_TOKEN'), 'from' => env('TWILIO_FROM')],
 ],
];
