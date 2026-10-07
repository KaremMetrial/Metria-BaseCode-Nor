<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentRefund extends Model {
 
 protected function casts(): array { return ['amount'=>'integer']; }
}
