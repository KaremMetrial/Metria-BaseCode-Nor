<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class PaymentReceived implements ShouldDispatchAfterCommit
{
    public function __construct(public int $paymentId) {}
}
