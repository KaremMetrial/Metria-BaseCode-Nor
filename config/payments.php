<?php

use App\Services\Payments\StripeGateway;

return [
    'default' => env('PAYMENT_PROVIDER', 'disabled'),
    'default_currency' => env('WALLET_DEFAULT_CURRENCY', 'EGP'),
    'currencies' => ['EGP', 'USD', 'SAR', 'AED', 'GBP', 'CAD'],
    'max_amount' => 99999999,
    'max_balance' => 9000000000000000,
    'providers' => [
        'stripe' => ['driver' => StripeGateway::class, 'secret' => env('STRIPE_SECRET'), 'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'), 'livemode' => (bool) env('STRIPE_LIVEMODE', true)],
    ],
];
