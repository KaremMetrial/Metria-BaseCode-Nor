<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model {
 protected $hidden = ['client_secret','request_hash','idempotency_key'];
 protected function casts(): array { return ['amount'=>'integer','refunded_amount'=>'integer','status'=>\App\Enums\PaymentStatus::class,'client_secret'=>'encrypted']; }
}
