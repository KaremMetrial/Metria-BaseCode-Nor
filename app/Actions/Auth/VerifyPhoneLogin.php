<?php

namespace App\Actions\Auth;

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
use App\Services\Wallet\WalletService;
use Illuminate\Database\UniqueConstraintViolationException;

final class VerifyPhoneLogin
{
    public function __construct(private readonly AuthenticationPhone $phones, private readonly OtpService $otp, private readonly AuditLoggerInterface $audit, private readonly WalletService $wallets) {}

    public function execute(array $data, UserType $type): array
    {
        $phone = $this->phones->parse((int) $data['country_id'], $data['phone']);
        try {
            return $this->otp->consume($data['challenge_id'], $phone->e164, $type, OtpPurpose::LOGIN, null, $data['code'], function (OtpChallenge $challenge) use ($phone, $type): array {
                $user = User::withTrashed()->where('phone', $phone->e164)->lockForUpdate()->first();
                if ($user && ($user->trashed() || $user->type !== $type || ! $user->status->canAuthenticate())) {
                    throw new DomainException(ErrorCode::INVALID_CREDENTIALS);
                }
                if (! $user) {
                    $user = new User;
                    $user->forceFill(['name' => '', 'phone' => $phone->e164, 'phone_country_id' => $challenge->country_id, 'type' => $type, 'status' => $type === UserType::VENDOR ? UserStatus::PENDING : UserStatus::ACTIVE, 'locale' => app()->getLocale()]);
                }
                $user->forceFill(['phone_verified_at' => now(), 'last_login_at' => now()])->save();
                $this->wallets->forUser($user, (string) config('payments.default_currency'));
                $token = $user->createToken($type->value, ['*'], now()->addMinutes((int) config('otp.token_minutes')))->plainTextToken;
                $this->audit->record(new AuditEntry(AuditAction::USER_LOGIN, $user, actor: $user));

                return ['user' => $user, 'token' => $token];
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(ErrorCode::PHONE_ALREADY_EXISTS);
        }
    }
}
