<?php

use App\Services\Payments\MyFatoorahGateway;
use App\Services\Payments\StripeGateway;

return [
    'default' => env('PAYMENT_PROVIDER', 'disabled'),
    'default_currency' => env('WALLET_DEFAULT_CURRENCY', 'EGP'),
    'currencies' => ['EGP', 'USD', 'SAR', 'AED', 'GBP', 'CAD', 'KWD', 'BHD', 'OMR', 'JOD', 'QAR'],
    'max_amount' => 99999999,
    'max_balance' => 9000000000000000,
    'providers' => [
        'stripe' => ['driver' => StripeGateway::class, 'secret' => env('STRIPE_SECRET'), 'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'), 'livemode' => (bool) env('STRIPE_LIVEMODE', true)],
        'myfatoorah' => [
            'driver' => MyFatoorahGateway::class,
            'secret' => env('MYFATOORAH_API_KEY'),
            'webhook_secret' => env('MYFATOORAH_WEBHOOK_SECRET'),
            'livemode' => (bool) env('MYFATOORAH_LIVE', false),
            'country' => env('MYFATOORAH_COUNTRY', 'KWT'),
            'currency' => env('MYFATOORAH_CURRENCY', 'KWD'),
            'redirect_url' => env('MYFATOORAH_REDIRECT_URL'),
            'idempotency_minutes' => 240,
        ],
    ],
];
