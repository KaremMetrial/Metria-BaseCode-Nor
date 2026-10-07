<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, string|int|float|bool|null> $parameters
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $published_at
 */
class UserNotification extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['parameters' => 'array', 'read_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime'];
    }

    /** @return array{id: string, title: string, body: string, read_at: string|null, created_at: string} */
    public function payload(): array
    {
        return ['id' => $this->id, 'title' => trans('notifications.title', [], $this->locale), 'body' => trans($this->translation_key, $this->parameters, $this->locale), 'read_at' => $this->read_at?->toIso8601String(), 'created_at' => $this->created_at->toIso8601String()];
    }
}
