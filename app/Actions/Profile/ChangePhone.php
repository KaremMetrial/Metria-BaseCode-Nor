<?php

namespace App\Actions\Profile;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Enums\OtpPurpose;
use App\Events\PhoneChanged;
use App\Exceptions\DomainException;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\Auth\AuthenticationPhone;
use App\Services\Auth\OtpService;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Database\UniqueConstraintViolationException;

final class ChangePhone
{
    public function __construct(private readonly AuthenticationPhone $phones, private readonly OtpService $otp, private readonly AuditLoggerInterface $audit) {}

    public function request(User $user, array $data): string
    {
        $phone = $this->phones->parse((int) $data['country_id'], $data['phone']);
        if (User::withTrashed()->where('phone', $phone->e164)->exists()) {
            throw new DomainException(ErrorCode::PHONE_ALREADY_EXISTS);
        }

        return $this->otp->issue($phone->e164, (int) $data['country_id'], $user->type, OtpPurpose::CHANGE_PHONE, $user->id, $user->preferredLocale());
    }

    public function verify(User $user, array $data): User
    {
        $phone = $this->phones->parse((int) $data['country_id'], $data['phone']);
        try {
            return $this->otp->consume($data['challenge_id'], $phone->e164, $user->type, OtpPurpose::CHANGE_PHONE, $user->id, $data['code'], function (OtpChallenge $challenge) use ($user, $phone): User {
                $locked = User::query()->lockForUpdate()->findOrFail($user->id);
                if (! $locked->isActive()) {
                    throw new DomainException(ErrorCode::ACCOUNT_DISABLED);
                }
                if (User::withTrashed()->where('phone', $phone->e164)->whereKeyNot($locked->id)->exists()) {
                    throw new DomainException(ErrorCode::PHONE_ALREADY_EXISTS);
                }
                $old = $locked->phone;
                $locked->forceFill(['phone' => $phone->e164, 'phone_country_id' => $challenge->country_id, 'phone_verified_at' => now()])->save();
                $locked->tokens()->delete();
                $this->audit->record(new AuditEntry(AuditAction::USER_PHONE_CHANGED, $locked, ['phone' => $old], ['phone' => $phone->e164], $locked));
                app(NotificationOutbox::class)->record($locked, 'notifications.phone_changed', [], 'phone:'.$challenge->challenge_id);
                event(new PhoneChanged($locked->id));

                return $locked;
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(ErrorCode::PHONE_ALREADY_EXISTS);
        }
    }
}
