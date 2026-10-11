<?php

namespace App\Actions\Admin\Payments;

use App\Actions\Shared\Payments\SettleRefund;
use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\DomainException;
use App\Exceptions\RefundRejectedException;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Payments\GatewayManager;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RefundPayment
{
    public function __construct(private readonly GatewayManager $gateways, private readonly WalletService $wallets, private readonly AuditLoggerInterface $audit, private readonly SettleRefund $settleRefund) {}

    public function execute(User $actor, Payment $payment, int $amount, string $key): PaymentRefund
    {
        if (! $actor->isAdmin() || ! $actor->isActive() || ! $actor->can('payments.refund')) {
            throw new DomainException(ErrorCode::FORBIDDEN);
        }
        if ($key === '' || strlen($key) > 128) {
            throw new DomainException(ErrorCode::VALIDATION_FAILED);
        }
        $this->wallets->amount($amount);
        $gateway = $this->gateways->get($payment->provider);
        $hash = hash('sha256', json_encode([$amount], JSON_THROW_ON_ERROR));
        $refund = DB::transaction(function () use ($actor, $payment, $amount, $key, $hash): PaymentRefund {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $existing = PaymentRefund::query()->where('payment_id', $locked->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw new DomainException(ErrorCode::IDEMPOTENCY_CONFLICT);
                }

                return $existing;
            }
            if ($locked->status === PaymentStatus::REFUNDED) {
                throw new DomainException(ErrorCode::PAYMENT_ALREADY_REFUNDED);
            }
            if (! in_array($locked->status, [PaymentStatus::PAID, PaymentStatus::PARTIALLY_REFUNDED], true)) {
                throw new DomainException(ErrorCode::INVALID_PAYMENT_STATUS);
            }
            $reserved = (int) PaymentRefund::query()->where('payment_id', $locked->id)->whereIn('status', ['pending', 'succeeded'])->sum('amount');
            if ($amount > $locked->amount - $reserved) {
                throw new DomainException(ErrorCode::INVALID_AMOUNT);
            }
            $refund = new PaymentRefund;
            $refund->forceFill(['uuid' => (string) Str::uuid(), 'payment_id' => $locked->id, 'actor_id' => $actor->id, 'amount' => $amount, 'status' => 'pending', 'idempotency_key' => $key, 'request_hash' => $hash])->save();
            $this->wallets->change(Wallet::query()->findOrFail($locked->wallet_id), WalletTransactionType::DEBIT, $amount, 'refund:'.$refund->uuid, 'payment_refund_reservation');
            $this->audit->record(new AuditEntry(AuditAction::PAYMENT_REFUND_REQUESTED, $refund, actor: $actor));

            return $refund;
        }, 5);
        if ($refund->status !== 'pending' || $refund->provider_reference !== null) {
            return $refund;
        }
        if ($refund->created_at->lt(now()->subMinutes((int) config('payments.providers.'.$payment->provider.'.idempotency_minutes', 1380)))) {
            throw new DomainException(ErrorCode::RECONCILIATION_REQUIRED);
        }
        // Timeouts leave the reservation pending. Retrying uses the same provider key.
        try {
            $result = $gateway->refund($payment, $refund);
        } catch (RefundRejectedException) {
            return $this->settleRefund->execute($refund, null, 'failed');
        }

        return $this->settleRefund->execute($refund, $result['reference'], $result['status']);
    }
}
