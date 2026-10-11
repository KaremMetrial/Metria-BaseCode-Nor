<?php

namespace App\Models;

use Database\Factories\ShareLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShareLink extends Model
{
    /** @use HasFactory<ShareLinkFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['revoked_at' => 'immutable_datetime'];
    }
}
