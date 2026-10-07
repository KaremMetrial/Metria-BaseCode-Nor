<?php

namespace App\Actions\Payments;

use App\Contracts\Payments\ReconcilesPayments;
use App\Enums\ErrorCode;
use App\Enums\PaymentStatus;
use App\Exceptions\DomainException;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Services\Payments\GatewayManager;

final class ReconcilePayment
{
    public function __construct(private readonly GatewayManager $gateways, private readonly ProcessWebhook $events) {}

    /** @return array{payment_id:int, settled:int, unresolved:int} */
    public function execute(Payment $payment): array
    {
        $gateway = $this->gateways->get($payment->provider);
        if (! $gateway instanceof ReconcilesPayments) {
            throw new DomainException(ErrorCode::RECONCILIATION_REQUIRED);
        }
        $settled = 0;
        $unresolved = 0;
        if (in_array($payment->status, [PaymentStatus::PENDING, PaymentStatus::PROCESSING], true)) {
            $event = $gateway->lookupPayment($payment);
            if ($event !== null && in_array($event['type'], ['payment.succeeded', 'payment.canceled'], true)) {
                $this->events->applyVerifiedEvent($payment->provider, $event);
                $settled++;
                $payment->refresh();
            } else {
                $unresolved++;
            }
        }
        foreach (PaymentRefund::query()->where('payment_id', $payment->id)->where('status', 'pending')->orderBy('updated_at')->orderBy('id')->limit(25)->get() as $refund) {
            try {
                $event = $gateway->lookupRefund($payment, $refund);
                if ($event !== null && in_array($event['status'], ['succeeded', 'failed', 'canceled'], true)) {
                    $this->events->applyVerifiedEvent($payment->provider, $event);
                    $settled++;
                } else {
                    $unresolved++;
                }
            } catch (DomainException) {
                $unresolved++;
            } finally {
                PaymentRefund::query()->whereKey($refund->id)->update(['updated_at' => now()]);
            }
        }

        return ['payment_id' => $payment->id, 'settled' => $settled, 'unresolved' => $unresolved];
    }
}
