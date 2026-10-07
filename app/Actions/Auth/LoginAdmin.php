<?php

namespace App\Actions\Auth;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Enums\UserType;
use App\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class LoginAdmin
{
    public function __construct(private readonly AuditLoggerInterface $audit) {}

    public function execute(string $email, #[\SensitiveParameter] string $password): array
    {
        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
        // A fixed dummy hash equalizes the expensive password check for unknown accounts.
        $hash = $user->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = Hash::check($password, $hash);
        if (! $valid || ! $user || $user->type !== UserType::ADMIN || ! $user->isActive()) {
            $this->audit->record(new AuditEntry(AuditAction::USER_LOGIN_FAILED));
            throw new DomainException(ErrorCode::INVALID_CREDENTIALS);
        }

        return DB::transaction(function () use ($user): array {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $user->isActive() || $user->type !== UserType::ADMIN) {
                throw new DomainException(ErrorCode::INVALID_CREDENTIALS);
            }
            $user->markAsLoggedIn();
            $token = $user->createToken('admin', ['*'], now()->addMinutes((int) config('otp.token_minutes')))->plainTextToken;
            $this->audit->record(new AuditEntry(AuditAction::USER_LOGIN, $user, actor: $user));

            return ['user' => $user, 'token' => $token];
        });
    }
}
