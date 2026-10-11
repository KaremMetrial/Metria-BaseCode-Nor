<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SupportMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property CarbonImmutable $created_at */
class SupportMessage extends Model
{
    /** @use HasFactory<SupportMessageFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return HasMany<Media, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /** @return BelongsTo<SupportConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
