<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property PaymentStatus $status
 * @property array{locale: string, redirect_url: ?string}|null $provider_request_context
 */
class Payment extends Model
{
    protected $hidden = ['client_secret', 'payment_url', 'request_hash', 'idempotency_key', 'provider_request_context'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'refunded_amount' => 'integer', 'status' => PaymentStatus::class, 'client_secret' => 'encrypted', 'payment_url' => 'encrypted', 'provider_request_context' => 'encrypted:array'];
    }
}
