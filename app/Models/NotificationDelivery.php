<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/** @property CarbonImmutable|null $available_at */
class NotificationDelivery extends Model
{
    protected function casts(): array
    {
        return ['attempts' => 'integer', 'available_at' => 'immutable_datetime'];
    }
}
