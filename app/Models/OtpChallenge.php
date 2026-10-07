<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpChallenge extends Model
{
    protected $hidden = ['code_hash', 'scope'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'attempts' => 'integer', 'expires_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime'];
    }
}
