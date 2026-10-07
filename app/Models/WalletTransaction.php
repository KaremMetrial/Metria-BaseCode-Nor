<?php

namespace App\Models;

use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property WalletTransactionType $direction
 */
class WalletTransaction extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Immutable record'));
        static::deleting(fn () => throw new \LogicException('Immutable record'));
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer', 'created_at' => 'immutable_datetime', 'direction' => WalletTransactionType::class];
    }
}
