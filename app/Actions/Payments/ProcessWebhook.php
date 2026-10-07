<?php

namespace App\Actions\Payments;

use App\Enums\ErrorCode;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Events\PaymentReceived;
use App\Exceptions\DomainException;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentWebhookEvent;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Payments\GatewayManager;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;

final class ProcessWebhook
{
    public function __construct(private readonly GatewayManager $gateways, private readonly WalletService $wallets, private readonly RefundPayment $refunds, private readonly NotificationOutbox $notifications) {}

    public function execute(string $provider, string $body, array $headers): void
    {
        $event = $this->gateways->get($provider)->verifyWebhook($body, $headers);
        $this->applyVerifiedEvent($provider, $event, $body);
    }

    /** Only verified gateway results may cross this internal boundary. */
    public function applyVerifiedEvent(string $provider, array $event, ?string $body = null): void
    {
        $body ??= json_encode($event, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($provider, $body, $event): void {
            $inserted = PaymentWebhookEvent::query()->insertOrIgnore(['provider' => $provider, 'provider_event_id' => $event['id'], 'payload_hash' => hash('sha256', $body), 'created_at' => now()]);
            if (! $inserted) {
                $previous = PaymentWebhookEvent::query()->where('provider', $provider)->where('provider_event_id', $event['id'])->firstOrFail();
                if (! hash_equals($previous->payload_hash, hash('sha256', $body))) {
                    throw new DomainException(ErrorCode::IDEMPOTENCY_CONFLICT);
                }

                return;
            }

            if ($event['type'] === 'refund.updated') {
                $refund = PaymentRefund::query()->where('uuid', $event['refund_uuid'] ?? '')->first();
                if (! $refund) {
                    throw new DomainException(ErrorCode::RECONCILIATION_REQUIRED);
                }
                $payment = Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
                if ($payment->provider !== $provider || $payment->provider_reference !== ($event['payment_reference'] ?? null) || $refund->amount !== ($event['amount'] ?? null) || $payment->currency !== strtoupper($event['currency'] ?? '')) {
                    throw new DomainException(ErrorCode::INVALID_WEBHOOK);
                }
                $status = match ($event['status'] ?? '') {
                    'succeeded' => 'succeeded',
                    'failed', 'canceled' => 'failed',
                    'pending', 'requires_action' => 'pending',
                    default => throw new DomainException(ErrorCode::INVALID_WEBHOOK)
                };
                $this->refunds->settle($refund, $event['reference'], $status);

                return;
            }
            if (! in_array($event['type'], ['payment.succeeded', 'payment.canceled'], true)) {
                return;
            }
            $payment = Payment::query()->where('uuid', $event['payment_uuid'] ?? '')->where('provider', $provider)->lockForUpdate()->first();
            if (! $payment) {
                throw new DomainException(ErrorCode::INVALID_WEBHOOK);
            }
            if (! is_string($event['reference'] ?? null) || ($payment->provider_reference !== null && $payment->provider_reference !== $event['reference']) || $payment->amount !== ($event['amount'] ?? null) || $payment->currency !== strtoupper($event['currency'] ?? '')) {
                throw new DomainException(ErrorCode::INVALID_WEBHOOK);
            }
            if ($event['type'] === 'payment.succeeded') {
                if (($event['status'] ?? '') !== 'succeeded' || ($event['received_amount'] ?? null) !== $payment->amount) {
                    throw new DomainException(ErrorCode::INVALID_WEBHOOK);
                }
                if (in_array($payment->status, [PaymentStatus::PAID, PaymentStatus::PARTIALLY_REFUNDED, PaymentStatus::REFUNDED], true)) {
                    return;
                }
                if (! in_array($payment->status, [PaymentStatus::PENDING, PaymentStatus::PROCESSING], true)) {
                    throw new DomainException(ErrorCode::INVALID_PAYMENT_STATUS);
                }
                $this->wallets->change(Wallet::query()->findOrFail($payment->wallet_id), WalletTransactionType::CREDIT, $payment->amount, 'payment:'.$payment->uuid, 'payment_received');
                $payment->forceFill(['provider_reference' => $event['reference'], 'status' => PaymentStatus::PAID])->save();
                $this->notifications->record(User::withTrashed()->findOrFail($payment->user_id), 'notifications.payment_received', ['amount' => sprintf('%d.%02d', intdiv($payment->amount, 100), $payment->amount % 100), 'currency' => $payment->currency], 'payment:'.$payment->uuid);
                event(new PaymentReceived($payment->id));
            } elseif (in_array($payment->status, [PaymentStatus::PENDING, PaymentStatus::PROCESSING], true)) {
                if (($event['status'] ?? '') !== 'canceled') {
                    throw new DomainException(ErrorCode::INVALID_WEBHOOK);
                }
                $payment->forceFill(['provider_reference' => $event['reference'], 'status' => PaymentStatus::FAILED])->save();
            }
        }, 5);
    }
}
