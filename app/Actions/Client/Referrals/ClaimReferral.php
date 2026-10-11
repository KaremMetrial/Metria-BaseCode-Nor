<?php

namespace App\Actions\Client\Referrals;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Enums\UserType;
use App\Exceptions\DomainException;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClaimReferral
{
    public function __construct(private AuditLoggerInterface $audit) {}

    public function execute(User $actor, string $code): Referral
    {
        return DB::transaction(function () use ($actor, $code): Referral {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            if ($user->type !== UserType::CLIENT || ! $user->isActive() || ! $user->hasVerifiedPhone()) {
                throw new DomainException(ErrorCode::FORBIDDEN);
            }
            $referralCode = ReferralCode::query()->where('code', strtoupper($code))->first();
            if (! $referralCode) {
                throw ValidationException::withMessages(['referral_code' => [__('links.invalid_referral')]]);
            }
            $existing = Referral::query()->where('referred_user_id', $user->id)->first();
            if ($existing) {
                if ($existing->referral_code_id !== $referralCode->id) {
                    throw new DomainException(ErrorCode::RESOURCE_CONFLICT);
                }

                return $existing;
            }
            if ($user->profile_completed_at !== null || $user->referral_claim_expires_at === null || ! $user->referral_claim_expires_at->isFuture()) {
                throw ValidationException::withMessages(['referral_code' => [__('links.ineligible_referral')]]);
            }
            $referrer = User::query()->active()->ofType(UserType::CLIENT)->whereNotNull('profile_completed_at')->whereNotNull('phone_verified_at')->find($referralCode->user_id);
            if (! $referrer || $referrer->id === $user->id) {
                throw ValidationException::withMessages(['referral_code' => [__('links.invalid_referral')]]);
            }
            $referral = new Referral;
            $referral->forceFill(['referral_code_id' => $referralCode->id, 'referrer_id' => $referrer->id, 'referred_user_id' => $user->id])->save();
            $this->audit->record(new AuditEntry(AuditAction::REFERRAL_CLAIMED, $referral, actor: $user));

            return $referral;
        }, 5);
    }
}
