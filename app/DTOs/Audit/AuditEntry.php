<?php

namespace App\DTOs\Audit;

use App\Enums\AuditAction;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything needed to write one audit row.
 *
 * A DTO rather than a long parameter list so the logger's contract stays
 * readable and new fields can be added without changing every call site.
 */
final readonly class AuditEntry
{
    /**
     * @param  array<string, mixed>  $oldValues  State before the change.
     * @param  array<string, mixed>  $newValues  State after the change.
     * @param  array<string, mixed>  $context  Extra non-sensitive detail (reason, channel, ...).
     */
    public function __construct(
        public AuditAction $action,
        public ?Model $subject = null,
        public array $oldValues = [],
        public array $newValues = [],
        public ?Authenticatable $actor = null,
        public array $context = [],
    ) {}
}
