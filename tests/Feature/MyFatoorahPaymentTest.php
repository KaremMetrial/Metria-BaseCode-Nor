<?php

namespace Tests\Feature;

use App\Actions\Payments\CreatePayment;
use App\Actions\Payments\ReconcilePayment;
use App\Actions\Payments\RefundPayment;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Operations\ReadinessChecks;
use App\Services\Payments\MyFatoorahClient;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MyFatoorahPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.providers.myfatoorah.secret' => 'sandbox-key', 'payments.providers.myfatoorah.webhook_secret' => 'webhook-secret', 'payments.providers.myfatoorah.currency' => 'KWD', 'payments.providers.myfatoorah.country' => 'KWT', 'payments.providers.myfatoorah.livemode' => false]);
    }

    private function payment(): Payment
    {
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/v3/payments' => Http::response(['IsSuccess' => true, 'Data' => ['InvoiceId' => '1001', 'PaymentURL' => 'https://demo.myfatoorah.com/checkout/1001']], 201)]);

        return app(CreatePayment::class)->execute(User::factory()->create(), 12345, 'KWD', 'myfatoorah', 'checkout-one');
    }

    private function snapshot(Payment $payment, string $status = 'SUCCESS'): array
    {
        return ['Invoice' => ['Id' => '1001', 'Status' => $status === 'SUCCESS' ? 'PAID' : 'PENDING', 'ExternalIdentifier' => $payment->uuid],
            'Transaction' => ['Status' => $status, 'PaymentId' => 'payment1001'],
            'Amount' => ['BaseCurrency' => 'KWD', 'ValueInBaseCurrency' => '12.345', 'ReceivableAmount' => '12.100']];
    }

    private function webhook(array $snapshot, string $secret = 'webhook-secret'): TestResponse
    {
        $canonical = 'Invoice.Id='.$snapshot['Invoice']['Id'].',Invoice.Status='.$snapshot['Invoice']['Status'].',Transaction.Status='.$snapshot['Transaction']['Status'].',Transaction.PaymentId='.$snapshot['Transaction']['PaymentId'].',Invoice.ExternalIdentifier='.$snapshot['Invoice']['ExternalIdentifier'];

        return $this->postJson('/api/v1/webhooks/payments/myfatoorah', ['Event' => ['Code' => 1, 'Reference' => 'event-reference'], 'Data' => $snapshot], ['MyFatoorah-Signature' => base64_encode(hash_hmac('sha256', $canonical, $secret, true))]);
    }

    public function test_hosted_checkout_replays_and_exposes_url_only_to_pending_owner(): void
    {
        $payment = $this->payment();
        Sanctum::actingAs(User::findOrFail($payment->user_id));
        $this->postJson('/api/v1/client/payments', ['amount' => 12345, 'currency' => 'KWD', 'provider' => 'myfatoorah'], ['Idempotency-Key' => 'checkout-one'])
            ->assertCreated()->assertJsonPath('data.payment_url', 'https://demo.myfatoorah.com/checkout/1001');
        Http::assertSent(fn ($request) => $request['Order']['Amount'] === 12.345 && $request['Order']['Currency'] === 'KWD'
            && $request['Order']['ExternalIdentifier'] === $payment->uuid && $request->hasHeader('Idempotency-Key', 'payment:'.$payment->uuid)
            && $request->hasHeader('Authorization', 'Bearer sandbox-key'));
        Http::assertSentCount(1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertStringNotContainsString('demo.myfatoorah', $payment->getRawOriginal('payment_url'));
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/client/payments/'.$payment->id)->assertNotFound();
    }

    public function test_failed_attempt_does_not_cancel_invoice_and_later_verified_payment_credits_once(): void
    {
        $payment = $this->payment();
        $failed = $this->snapshot($payment, 'FAILED');
        $paid = $this->snapshot($payment);
        Http::fake(['https://apitest.myfatoorah.com/v3/payments/payment1001' => Http::sequence()->push(['IsSuccess' => true, 'Data' => $failed])->push(['IsSuccess' => true, 'Data' => $paid])->push(['IsSuccess' => true, 'Data' => $paid])]);
        $this->webhook($failed)->assertOk();
        $this->assertSame(PaymentStatus::PENDING, $payment->fresh()->status);
        $this->webhook($paid)->assertOk();
        $this->webhook($paid)->assertOk();
        $this->assertSame(12345, Wallet::findOrFail($payment->wallet_id)->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
        Sanctum::actingAs(User::findOrFail($payment->user_id));
        $this->getJson('/api/v1/client/payments/'.$payment->id)->assertOk()->assertJsonMissingPath('data.payment_url');
        Http::assertSentCount(3);
    }

    public function test_unsigned_amount_and_forged_signature_cannot_credit_a_wallet(): void
    {
        $payment = $this->payment();
        $snapshot = $this->snapshot($payment);
        $this->webhook($snapshot, 'wrong-secret')->assertBadRequest();
        Http::assertSentCount(1);
        $snapshot['Amount']['ValueInBaseCurrency'] = '999';
        Http::fake(['https://apitest.myfatoorah.com/v3/payments/payment1001' => Http::response(['IsSuccess' => true, 'Data' => $snapshot])]);
        $body = $this->snapshot($payment);
        $this->webhook($body)->assertBadRequest();
        $this->assertSame(0, Wallet::findOrFail($payment->wallet_id)->balance);
        $this->assertDatabaseCount('wallet_transactions', 0);
        Http::assertSentCount(1);
    }

    public function test_currency_mismatch_is_rejected_before_creating_remote_invoice(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/v3/payments' => Http::response([])]);
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/client/payments', ['amount' => 100, 'currency' => 'EGP', 'provider' => 'myfatoorah'], ['Idempotency-Key' => 'currency-mismatch'])->assertUnprocessable()->assertJsonPath('code', 'CURRENCY_MISMATCH');
        Http::assertNothingSent();
    }

    public function test_ambiguous_create_stops_retries_before_provider_key_expiry_and_reconciles_by_uuid(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/v3/payments' => Http::failedConnection()]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $body = ['amount' => 12345, 'currency' => 'KWD', 'provider' => 'myfatoorah'];
        $this->postJson('/api/v1/client/payments', $body, ['Idempotency-Key' => 'timeout'])->assertServiceUnavailable();
        $payment = Payment::firstOrFail();
        $this->travel(241)->minutes();
        $this->postJson('/api/v1/client/payments', $body, ['Idempotency-Key' => 'timeout'])->assertConflict()->assertJsonPath('code', 'RECONCILIATION_REQUIRED');
        $snapshot = $this->snapshot($payment);
        $snapshot['Transactions'] = [$snapshot['Transaction']];
        unset($snapshot['Transaction']);
        Http::fake(['https://apitest.myfatoorah.com/v3/invoices/externalIdentifier/'.$payment->uuid => Http::response(['IsSuccess' => true, 'Data' => $snapshot])]);
        app(ReconcilePayment::class)->execute($payment);
        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);
        $this->assertSame(12345, Wallet::findOrFail($payment->wallet_id)->balance);
        Http::assertSentCount(1);
    }

    public function test_partial_refund_reserves_then_settles_from_authoritative_status(): void
    {
        $payment = $this->payment();
        $snapshot = $this->snapshot($payment);
        Http::fake(['https://apitest.myfatoorah.com/v3/payments/payment1001' => Http::response(['IsSuccess' => true, 'Data' => $snapshot])]);
        $this->webhook($snapshot)->assertOk();
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->assignRole('finance-admin');
        $snapshot['Transactions'] = [$snapshot['Transaction']];
        $refundData = function (string $status): array {
            return ['Refund' => ['Id' => 'refund1001', 'Status' => $status, 'ExternalIdentifier' => PaymentRefund::firstOrFail()->uuid], 'Amount' => ['BaseCurrency' => 'KWD', 'ValueInBaseCurrency' => '2.345'], 'ReferencedInvoice' => ['Id' => '1001']];
        };
        Http::fake([
            'https://apitest.myfatoorah.com/v3/invoices/1001' => Http::response(['IsSuccess' => true, 'Data' => $snapshot]),
            'https://apitest.myfatoorah.com/v3/refunds' => fn ($request) => Http::response(['IsSuccess' => true, 'Data' => $refundData('Pending')], 201),
            'https://apitest.myfatoorah.com/v3/refunds/refund1001' => fn () => Http::response(['IsSuccess' => true, 'Data' => $refundData('Refunded')]),
        ]);
        $refund = app(RefundPayment::class)->execute($admin, $payment->fresh(), 2345, 'partial');
        $this->assertSame('pending', $refund->status);
        $this->assertSame(10000, Wallet::findOrFail($payment->wallet_id)->balance);
        app(ReconcilePayment::class)->execute($payment->fresh());
        $this->assertSame('succeeded', $refund->fresh()->status);
        $this->assertSame(2345, $payment->fresh()->refunded_amount);
        Http::assertSent(fn ($request) => $request->url() === 'https://apitest.myfatoorah.com/v3/refunds' && $request['Amount'] === 2.345 && $request['PaymentId'] === 'payment1001');
        Http::assertSentCount(3);
    }

    #[DataProvider('regions')]
    public function test_region_selection_uses_official_live_hosts(string $country, string $host): void
    {
        $client = new MyFatoorahClient(['country' => $country, 'livemode' => true]);
        $this->assertSame('https://'.$host, $client->baseUrl());
    }

    public static function regions(): array
    {
        return [['KWT', 'api.myfatoorah.com'], ['BHR', 'api.myfatoorah.com'], ['JOR', 'api.myfatoorah.com'], ['OMN', 'api.myfatoorah.com'], ['ARE', 'api-ae.myfatoorah.com'], ['SAU', 'api-sa.myfatoorah.com'], ['QAT', 'api-qa.myfatoorah.com'], ['EGY', 'api-eg.myfatoorah.com']];
    }

    public function test_myfatoorah_readiness_requires_matching_country_currency_and_live_mode(): void
    {
        config(['payments.default' => 'myfatoorah']);
        $checks = app(ReadinessChecks::class);
        $this->assertTrue($checks->run(false, true)['payments_configured']);
        $this->assertFalse($checks->run(false)['payments_configured']);
        config(['payments.providers.myfatoorah.currency' => 'EGP']);
        $this->assertFalse($checks->run(false, true)['payments_configured']);
        config(['payments.providers.myfatoorah.country' => 'EGY']);
        $this->assertTrue($checks->run(false, true)['payments_configured']);
    }
}
