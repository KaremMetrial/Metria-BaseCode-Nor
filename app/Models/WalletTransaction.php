<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WalletTransaction extends Model {
 public const UPDATED_AT = null;
 protected function casts(): array { return ['amount'=>'integer','balance_after'=>'integer','created_at'=>'immutable_datetime','direction'=>\App\Enums\WalletTransactionType::class]; }
}
