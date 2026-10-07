<?php

namespace App\Actions\Auth;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Contracts\HasAbilities;
use Laravel\Sanctum\PersonalAccessToken;

final class Logout
{
    public function __construct(private readonly AuditLoggerInterface $audit) {}

    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            /** @var HasAbilities|null $token */
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }
            $this->audit->record(new AuditEntry(AuditAction::USER_LOGOUT, $user, actor: $user));
        });
    }
}
