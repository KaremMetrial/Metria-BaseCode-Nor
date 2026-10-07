<?php

namespace App\Contracts\Payments;

use App\Models\Payment;
use App\Models\PaymentRefund;

interface PaymentGatewayInterface
{
    /** @return array{reference:string,client_secret:string} */
    public function create(Payment $payment): array;

    /** @return array{reference:string,status:string} status: succeeded, pending, failed */
    public function refund(Payment $payment, PaymentRefund $refund): array;

    /** Verified, provider-neutral event. Invalid signatures must throw. */
    public function verifyWebhook(string $body, array $headers): array;
}
