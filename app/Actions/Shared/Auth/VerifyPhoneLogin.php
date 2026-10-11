<?php

namespace App\Actions\Shared\Auth;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Exceptions\DomainException;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\Auth\AuthenticationPhone;
use App\Services\Auth\OtpService;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Wallet\WalletService;
use Illuminate\Database\UniqueConstraintViolationException;

final class VerifyPhoneLogin
{
    public function __construct(private readonly AuthenticationPhone $phones, private readonly OtpService $otp, private readonly AuditLoggerInterface $audit, private readonly WalletService $wallets, private readonly NotificationOutbox $notifications) {}

    public function execute(array $data, UserType $type): array
    {
        $phone = $this->phones->parse((int) $data['country_id'], $data['phone']);
        try {
            return $this->otp->consume($data['challenge_id'], $phone->e164, $type, OtpPurpose::LOGIN, null, $data['code'], function (OtpChallenge $challenge) use ($phone, $type, $data): array {
                $user = User::withTrashed()->where('phone', $phone->e164)->lockForUpdate()->first();
                if ($user && ($user->trashed() || $user->type !== $type || ! $user->status->canAuthenticate())) {
                    throw new DomainException(ErrorCode::INVALID_CREDENTIALS);
                }
                $isNew = $user === null;
                if (! $user) {
                    $user = new User;
                    $user->forceFill(['name' => '', 'phone' => $phone->e164, 'phone_country_id' => $challenge->country_id, 'type' => $type, 'status' => $type === UserType::VENDOR ? UserStatus::PENDING : UserStatus::ACTIVE, 'locale' => app()->getLocale()]);
                }
                $user->forceFill(['phone_verified_at' => now(), 'last_login_at' => now()])->save();
                $this->wallets->forUser($user, (string) config('payments.default_currency'));
                if (isset($data['device_id'])) {
                    $user->tokens()->where('device_id', $data['device_id'])->delete();
                }
                $limit = (int) config('client.max_sessions');
                foreach ($user->tokens()->orderBy('id')->limit(max(0, $user->tokens()->count() - $limit + 1))->get() as $oldToken) {
                    $oldToken->delete();
                }
                $expiresAt = now()->addMinutes((int) config('otp.token_minutes'));
                $token = $user->createToken($data['device_name'] ?? $type->value, ['*'], $expiresAt);
                $token->accessToken->forceFill(['device_id' => $data['device_id'] ?? null, 'authenticated_at' => now()])->save();
                if ($type === UserType::CLIENT) {
                    $this->notifications->record($user, $isNew ? 'notifications.welcome' : 'notifications.new_login', [], 'login:'.$token->accessToken->id, 'security', ['screen' => 'sessions']);
                }
                $this->audit->record(new AuditEntry(AuditAction::USER_LOGIN, $user, actor: $user));

                return ['user' => $user, 'token' => $token->plainTextToken, 'expires_at' => $expiresAt->toIso8601String(), 'next_step' => $type === UserType::CLIENT && ! $user->hasCompletedProfile() ? 'complete_profile' : 'home'];
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(ErrorCode::PHONE_ALREADY_EXISTS);
        }
    }
}
