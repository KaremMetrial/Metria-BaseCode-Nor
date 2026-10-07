<?php
return [
 'default' => env('PAYMENT_PROVIDER','disabled'),
 'currencies' => ['EGP','USD','SAR','AED','GBP','CAD'],
 'max_amount' => 99999999,
 'max_balance' => 9000000000000000,
 'providers' => [
  'stripe' => ['driver'=>App\Services\Payments\StripeGateway::class,'secret'=>env('STRIPE_SECRET'),'webhook_secret'=>env('STRIPE_WEBHOOK_SECRET'),'livemode'=>(bool)env('STRIPE_LIVEMODE',true)],
 ],
];
