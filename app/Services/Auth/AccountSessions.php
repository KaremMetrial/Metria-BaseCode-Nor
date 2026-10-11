<?php

namespace App\Services\Auth;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Contracts\HasAbilities;
use Laravel\Sanctum\PersonalAccessToken;

final class AccountSessions
{
    public function current(User $user): PersonalAccessToken
    {
        $token = $this->persistedToken($user->currentAccessToken());
        $current = $user->tokens()->whereKey($token->getKey())->first();
        if (! $current || ! $this->isValid($current)) {
            throw new DomainException(ErrorCode::UNAUTHENTICATED);
        }

        return $current;
    }

    private function persistedToken(?HasAbilities $token): PersonalAccessToken
    {
        if (! $token instanceof PersonalAccessToken) {
            throw new DomainException(ErrorCode::UNAUTHENTICATED);
        }

        return $token;
    }

    public function isValid(PersonalAccessToken $token): bool
    {
        $expiration = (int) config('sanctum.expiration');

        return (! $token->expires_at || $token->expires_at->isFuture())
            && ($expiration === 0 || $token->created_at->gt(now()->subMinutes($expiration)));
    }

    public function requireRecent(User $user): void
    {
        $token = $this->current($user);
        $authenticatedAt = $token->getAttribute('authenticated_at');
        if (! $authenticatedAt || Carbon::parse($authenticatedAt)->lte(now()->subMinutes((int) config('client.recent_auth_minutes')))) {
            throw new DomainException(ErrorCode::REAUTHENTICATION_REQUIRED);
        }
    }

    public function revoke(User $user, ?int $sessionId = null): void
    {
        DB::transaction(function () use ($user, $sessionId): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $tokens = $user->tokens();
            if ($sessionId !== null) {
                $token = $tokens->whereKey($sessionId)->firstOrFail();
                $token->delete();
                DeviceToken::query()->where('user_id', $user->id)->whereNull('personal_access_token_id')->delete();
            } else {
                DeviceToken::query()->where('user_id', $user->id)->delete();
                $tokens->delete();
            }
        }, 5);
    }
}
