<?php

namespace App\Actions\Shared\Auth;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Auth\AccountSessions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Contracts\HasAbilities;
use Laravel\Sanctum\PersonalAccessToken;

final class Logout
{
    public function __construct(private readonly AuditLoggerInterface $audit, private readonly AccountSessions $sessions) {}

    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            /** @var HasAbilities|null $token */
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $this->sessions->revoke($user, (int) $token->getKey());
            }
            $this->audit->record(new AuditEntry(AuditAction::USER_LOGOUT, $user, actor: $user));
        });
    }
}
