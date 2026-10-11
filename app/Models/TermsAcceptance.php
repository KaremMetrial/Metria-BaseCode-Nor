<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermsAcceptance extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['accepted_at' => 'immutable_datetime'];
    }
}
