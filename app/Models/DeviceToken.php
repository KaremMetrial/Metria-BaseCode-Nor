<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $hidden = ['token', 'fingerprint'];

    protected function casts(): array
    {
        return ['token' => 'encrypted'];
    }
}
