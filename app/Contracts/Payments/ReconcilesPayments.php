<?php

namespace App\Contracts\Payments;

use App\Models\Payment;
use App\Models\PaymentRefund;

/** Read authoritative provider state without initiating a charge or refund. */
interface ReconcilesPayments
{
    /** Return a verified normalized event, or null when the outcome is still unknown. */
    public function lookupPayment(Payment $payment): ?array;

    /** Return a verified normalized event, or null; absence never releases a reservation. */
    public function lookupRefund(Payment $payment, PaymentRefund $refund): ?array;
}
