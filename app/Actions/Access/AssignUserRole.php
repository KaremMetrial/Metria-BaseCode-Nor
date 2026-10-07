<?php

namespace App\Actions\Access;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Support\Access\RoleRegistry;
use Illuminate\Support\Facades\DB;

final class AssignUserRole
{
    public function __construct(private readonly AuditLoggerInterface $audit) {}

    public function execute(User $actor, User $user, string $role): void
    {
        if (! $actor->isAdmin() || ! $actor->isActive() || ! $actor->hasRole(RoleRegistry::SUPER_ADMIN) || ! $user->isAdmin() || ! in_array($role, RoleRegistry::all(), true)) {
            throw new DomainException(ErrorCode::FORBIDDEN);
        }
        DB::transaction(function () use ($actor, $user, $role): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
                $this->audit->record(new AuditEntry(AuditAction::ROLE_ASSIGNED, $user, newValues: ['role' => $role], actor: $actor));
            }
        }, 5);
    }
}
