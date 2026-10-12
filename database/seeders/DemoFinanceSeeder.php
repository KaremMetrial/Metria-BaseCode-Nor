<?php

namespace Database\Seeders;

use App\Actions\Shared\Payments\SettleRefund;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Str;

/** Historical local examples: provider=demo has no gateway and is never reconciled. */
final class DemoFinanceSeeder extends DevelopmentSeeder
{
    protected function seed(): void
    {
        $wallets = app(WalletService::class);
        $currency = (string) config('payments.default_currency');
        foreach (['client', 'vendor'] as $key) {
            $user = $this->user($key);
            $wallet = $wallets->forUser($user, $currency);
            $wallets->change($wallet, WalletTransactionType::CREDIT, 100000, 'demo:opening-credit', 'demo_opening_balance');
            $wallets->change($wallet, WalletTransactionType::DEBIT, 12500, 'demo:purchase', 'demo_purchase');
            foreach (['paid' => [25000, 0], 'partially_refunded' => [20000, 5000], 'refunded' => [10000, 10000], 'failed' => [5000, 0]] as $scenario => [$amount, $refundAmount]) {
                $payment = Payment::query()->firstOrCreate(['user_id' => $user->id, 'idempotency_key' => 'demo:payment:'.$scenario], [
                    'uuid' => (string) Str::uuid(), 'wallet_id' => $wallet->id, 'provider' => 'demo',
                    'provider_reference' => 'demo:'.$key.':'.$scenario, 'amount' => $amount, 'currency' => $currency,
                    'status' => $scenario === 'failed' ? PaymentStatus::FAILED : PaymentStatus::PAID,
                    'request_hash' => hash('sha256', json_encode([$amount, $currency, 'demo'], JSON_THROW_ON_ERROR)),
                ]);
                if (! $payment->wasRecentlyCreated || $scenario === 'failed') {
                    continue;
                }
                $wallets->change($wallet, WalletTransactionType::CREDIT, $amount, 'payment:'.$payment->uuid, 'payment_received');
                if ($refundAmount > 0) {
                    $refund = PaymentRefund::query()->create(['uuid' => (string) Str::uuid(), 'payment_id' => $payment->id,
                        'actor_id' => $this->user('admin')->id, 'amount' => $refundAmount, 'status' => 'pending',
                        'idempotency_key' => 'demo:refund:'.$scenario, 'request_hash' => hash('sha256', json_encode([$refundAmount], JSON_THROW_ON_ERROR))]);
                    $wallets->change($wallet, WalletTransactionType::DEBIT, $refundAmount, 'refund:'.$refund->uuid, 'payment_refund_reservation');
                    app(SettleRefund::class)->execute($refund, 'demo:refund:'.$refund->uuid, 'succeeded');
                }
            }
        }
        $blocked = $this->user('blocked');
        if (! Wallet::query()->where('user_id', $blocked->id)->where('currency', $currency)->exists()) {
            $wallets->forUser($blocked, $currency)->forceFill(['is_locked' => true])->save();
        }
    }
}
