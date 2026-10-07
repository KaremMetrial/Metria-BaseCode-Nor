<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Wallet extends Model {
 
 protected function casts(): array { return ['balance'=>'integer','is_locked'=>'boolean']; }
}
