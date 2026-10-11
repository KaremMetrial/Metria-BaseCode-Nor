<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property CarbonImmutable|null $expires_at */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'media';

    protected function casts(): array
    {
        return ['size' => 'integer', 'width' => 'integer', 'height' => 'integer', 'expires_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<SupportMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class, 'support_message_id');
    }

    public function isAvailable(): bool
    {
        return in_array($this->status, ['ready', 'attached'], true) && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
