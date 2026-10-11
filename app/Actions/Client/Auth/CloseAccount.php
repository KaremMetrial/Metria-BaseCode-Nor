<?php

namespace App\Actions\Client\Auth;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Enums\PaymentStatus;
use App\Exceptions\DomainException;
use App\Models\OtpChallenge;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Auth\AccountSessions;
use Illuminate\Support\Facades\DB;

final class CloseAccount
{
    public function __construct(private readonly AccountSessions $sessions, private readonly AuditLoggerInterface $audit) {}

    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->sessions->requireRecent($user);
            $payments = Payment::query()->where('user_id', $user->id)->orderBy('id')->lockForUpdate()->get();
            $wallets = Wallet::query()->where('user_id', $user->id)->orderBy('id')->lockForUpdate()->get();
            if ($wallets->contains(fn (Wallet $wallet): bool => $wallet->balance !== 0)
                || $payments->contains(fn (Payment $payment): bool => in_array($payment->status, [PaymentStatus::PENDING, PaymentStatus::PROCESSING], true))
                || PaymentRefund::query()->whereIn('payment_id', $payments->modelKeys())->where('status', 'pending')->exists()) {
                throw new DomainException(ErrorCode::ACCOUNT_CLOSURE_BLOCKED);
            }
            foreach ($wallets as $wallet) {
                $wallet->forceFill(['is_locked' => true])->save();
            }
            $this->sessions->revoke($locked);
            OtpChallenge::query()->where('user_id', $user->id)->orWhere('phone', $locked->phone)->update(['consumed_at' => now()]);
            $this->audit->record(new AuditEntry(AuditAction::USER_ACCOUNT_CLOSED, $locked, actor: $locked));
            $locked->delete();
        }, 5);
    }
}
