<?php

namespace App\Actions\Access;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ChangeUserStatus
{
    public function __construct(private readonly AuditLoggerInterface $audit) {}

    public function execute(User $actor, User $user, UserStatus $status): User
    {
        return DB::transaction(function () use ($actor, $user, $status): User {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($actor->type !== UserType::ADMIN || ! $actor->status->isFullyActive() || $actor->id === $user->id || $user->type === UserType::ADMIN) {
                throw new DomainException(ErrorCode::FORBIDDEN);
            }
            $before = $user->status;
            $permission = match (true) {
                $user->type === UserType::VENDOR && $status === UserStatus::ACTIVE => 'vendors.approve',
                $user->type === UserType::VENDOR && $before === UserStatus::PENDING => 'vendors.reject',
                default => 'users.block',
            };
            if (! $actor->can($permission)) {
                throw new DomainException(ErrorCode::FORBIDDEN);
            }
            $user->forceFill(['status' => $status])->save();
            if (! $status->isFullyActive()) {
                $user->tokens()->delete();
            }
            $action = $user->type === UserType::VENDOR && $before === UserStatus::PENDING ? ($status === UserStatus::ACTIVE ? AuditAction::VENDOR_APPROVED : AuditAction::VENDOR_REJECTED) : AuditAction::USER_STATUS_CHANGED;
            $this->audit->record(new AuditEntry($action, $user, ['status' => $before->value], ['status' => $status->value], $actor));

            return $user;
        }, 5);
    }
}
