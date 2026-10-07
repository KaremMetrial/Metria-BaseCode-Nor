<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['parameters' => 'array', 'read_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime'];
    }

    public function payload(): array
    {
        return ['id' => $this->id, 'title' => trans('notifications.title', [], $this->locale), 'body' => trans($this->translation_key, $this->parameters, $this->locale), 'read_at' => $this->read_at?->toIso8601String(), 'created_at' => $this->created_at->toIso8601String()];
    }
}
