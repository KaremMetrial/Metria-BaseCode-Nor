<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An append-only audit record.
 *
 * `actor_user_id`, `subject_*` and `request_id` make a row joinable back to the
 * actor, the affected record and the originating HTTP request.
 *
 * @property AuditAction $action
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property array<string, mixed>|null $context
 */
#[Fillable([
    'actor_user_id',
    'action',
    'subject_type',
    'subject_id',
    'old_values',
    'new_values',
    'context',
    'ip_address',
    'user_agent',
    'request_id',
])]
class AuditLog extends Model
{
    /**
     * Immutable: a row is never updated after it is written.
     */
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Immutable record'));
        static::deleting(fn () => throw new \LogicException('Immutable record'));
    }

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The user who performed the action (null if they were later deleted).
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * The record that was acted upon.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
