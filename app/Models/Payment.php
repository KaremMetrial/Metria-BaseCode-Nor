<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

/** @property PaymentStatus $status */
class Payment extends Model
{
    protected $hidden = ['client_secret', 'payment_url', 'request_hash', 'idempotency_key'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'refunded_amount' => 'integer', 'status' => PaymentStatus::class, 'client_secret' => 'encrypted', 'payment_url' => 'encrypted'];
    }
}
