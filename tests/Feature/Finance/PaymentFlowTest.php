<?php

namespace Tests\Feature\Finance;

use App\Actions\Payments\CreatePayment;
use App\Actions\Payments\ProcessWebhook;
use App\Actions\Payments\RefundPayment;
use App\Enums\PaymentStatus;
use App\Exceptions\DomainException;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Payments\StripeGateway;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function provider(): void
    {
        config(['payments.providers.stripe' => ['driver' => StripeGateway::class, 'secret' => 'test-key', 'webhook_secret' => 'test-webhook-secret', 'livemode' => false]]);
        Http::preventStrayRequests();
    }

    private function createPayment(): Payment
    {
        $this->provider();
        $user = User::factory()->create();
        Http::fake(['https://api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_test', 'amount' => 10000, 'currency' => 'egp', 'client_secret' => 'secret_test'])]);

        return app(CreatePayment::class)->execute($user, 10000, 'EGP', 'stripe', 'create-key');
    }

    private function webhook(Payment $payment, string $id = 'evt_one', array $overrides = []): TestResponse
    {
        $body = json_encode(['id' => $id, 'type' => 'payment_intent.succeeded', 'livemode' => false, 'data' => ['object' => array_merge(['id' => 'pi_test', 'amount' => 10000, 'amount_received' => 10000, 'currency' => 'egp', 'status' => 'succeeded', 'metadata' => ['payment_uuid' => $payment->uuid]], $overrides)]], JSON_THROW_ON_ERROR);
        $time = now()->timestamp;
        $signature = 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, 'test-webhook-secret');

        return $this->call('POST', '/api/v1/webhooks/payments/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body);
    }

    public function test_payment_creation_replays_one_provider_operation_and_requires_same_payload(): void
    {
        $p = $this->createPayment();
        $same = app(CreatePayment::class)->execute(User::find($p->user_id), 10000, 'EGP', 'stripe', 'create-key');
        $this->assertSame($p->id, $same->id);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('payments', 1);
        Sanctum::actingAs(User::find($p->user_id));
        $this->postJson('/api/v1/client/payments', ['amount' => 999, 'currency' => 'EGP', 'provider' => 'stripe'], ['Idempotency-Key' => 'create-key'])->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
    }

    public function test_duplicate_and_distinct_success_events_credit_once_and_record_notification(): void
    {
        $p = $this->createPayment();
        $this->webhook($p)->assertOk();
        $this->webhook($p)->assertOk();
        $this->webhook($p, 'evt_two')->assertOk();
        $this->assertSame(PaymentStatus::PAID, $p->fresh()->status);
        $this->assertSame(10000, Wallet::find($p->wallet_id)->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('payment_webhook_events', 2);
        $this->assertDatabaseCount('user_notifications', 1);
    }

    public function test_bad_signature_and_wrong_amount_never_credit_wallet(): void
    {
        $p = $this->createPayment();
        $this->postJson('/api/v1/webhooks/payments/stripe', [])->assertBadRequest()->assertJsonPath('code', 'INVALID_WEBHOOK');
        $this->webhook($p, 'evt_wrong', ['amount' => 20000])->assertBadRequest();
        $this->assertSame(0, Wallet::find($p->wallet_id)->balance);
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_refund_is_reserved_once_and_supports_partial_then_full_refund(): void
    {
        $p = $this->createPayment();
        $this->webhook($p)->assertOk();
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->assignRole('finance-admin');
        Http::fake(['https://api.stripe.com/v1/refunds' => Http::sequence()->push(['id' => 're_one', 'status' => 'succeeded'])->push(['id' => 're_two', 'status' => 'succeeded'])]);
        $first = app(RefundPayment::class)->execute($admin, $p->fresh(), 4000, 'refund-one');
        $same = app(RefundPayment::class)->execute($admin, $p->fresh(), 4000, 'refund-one');
        $this->assertSame($first->id, $same->id);
        $this->assertSame(PaymentStatus::PARTIALLY_REFUNDED, $p->fresh()->status);
        app(RefundPayment::class)->execute($admin, $p->fresh(), 6000, 'refund-two');
        $this->assertSame(PaymentStatus::REFUNDED, $p->fresh()->status);
        $this->assertSame(0, Wallet::find($p->wallet_id)->balance);
        $this->assertDatabaseCount('payment_refunds', 2);
        $this->assertDatabaseCount('wallet_transactions', 3);
    }

    public function test_refund_timeout_preserves_reservation_and_retry_does_not_debit_twice(): void
    {
        $p = $this->createPayment();
        $this->webhook($p)->assertOk();
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->assignRole('finance-admin');
        $action = app(RefundPayment::class);
        Http::fake(['https://api.stripe.com/v1/refunds' => Http::sequence()->pushFailedConnection()->push(['id' => 're_one', 'status' => 'succeeded'])]);
        try {
            $action->execute($admin, $p->fresh(), 4000, 'refund');
            $this->fail('Expected provider failure');
        } catch (DomainException $e) {
            $this->assertSame('PROVIDER_UNAVAILABLE', $e->errorCode()->value);
        }
        $this->assertSame(6000, Wallet::find($p->wallet_id)->balance);
        $this->assertSame('pending', PaymentRefund::first()->status);
        $action->execute($admin, $p->fresh(), 4000, 'refund');
        $this->assertSame(6000, Wallet::find($p->wallet_id)->balance);
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_payment_owner_scope_and_client_status_input(): void
    {
        $p = $this->createPayment();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/client/payments/'.$p->id)->assertNotFound();
        $this->postJson('/api/v1/client/payments', ['amount' => 100, 'currency' => 'EGP', 'provider' => 'stripe', 'status' => 'paid'], ['Idempotency-Key' => 'bad'])->assertUnprocessable();
    }

    public function test_unknown_provider_fails_closed(): void
    {
        $this->expectException(DomainException::class);
        app(CreatePayment::class)->execute(User::factory()->create(), 100, 'EGP', 'missing', 'key');
    }

    public function test_multiple_provider_configurations_keep_payments_and_credentials_separate(): void
    {
        $this->provider();
        $secondary = config('payments.providers.stripe');
        $secondary['secret'] = 'secondary-test-key';
        config(['payments.providers.secondary' => $secondary]);
        Http::fake(['https://api.stripe.com/v1/payment_intents' => fn ($r) => Http::response(['id' => 'pi_'.$r['metadata']['payment_uuid'], 'amount' => 100, 'currency' => 'egp', 'client_secret' => 'secret'])]);
        $user = User::factory()->create();
        $action = app(CreatePayment::class);
        $first = $action->execute($user, 100, 'EGP', 'stripe', 'one');
        $second = $action->execute($user, 100, 'EGP', 'secondary', 'two');
        $this->assertSame('stripe', $first->provider);
        $this->assertSame('secondary', $second->provider);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer secondary-test-key'));
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_failed_refund_releases_reservation_once(): void
    {
        $p = $this->createPayment();
        $this->webhook($p)->assertOk();
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->assignRole('finance-admin');
        Http::fake(['https://api.stripe.com/v1/refunds' => Http::response(['id' => 're_failed', 'status' => 'failed'])]);
        $action = app(RefundPayment::class);
        $refund = $action->execute($admin, $p->fresh(), 4000, 'failed-refund');
        $action->execute($admin, $p->fresh(), 4000, 'failed-refund');
        $this->assertSame('failed', $refund->status);
        $this->assertSame(10000, Wallet::find($p->wallet_id)->balance);
        $this->assertSame(0, $p->fresh()->refunded_amount);
        $this->assertDatabaseCount('wallet_transactions', 3);
    }

    public function test_rejected_refund_releases_reservation_once_without_a_provider_reference(): void
    {
        $payment = $this->createPayment();
        $this->webhook($payment)->assertOk();
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->assignRole('finance-admin');
        Http::fake(['https://api.stripe.com/v1/refunds' => Http::response(['error' => ['type' => 'invalid_request_error', 'code' => 'amount_too_large']], 400)]);
        $action = app(RefundPayment::class);

        $refund = $action->execute($admin, $payment->fresh(), 4000, 'rejected-refund');
        $replayed = $action->execute($admin, $payment->fresh(), 4000, 'rejected-refund');

        $this->assertSame($refund->id, $replayed->id);
        $this->assertDatabaseHas('payment_refunds', ['id' => $refund->id, 'status' => 'failed', 'provider_reference' => null]);
        $this->assertSame(10000, Wallet::findOrFail($payment->wallet_id)->balance);
        $this->assertSame(0, $payment->fresh()->refunded_amount);
        $this->assertDatabaseCount('wallet_transactions', 3);
        Http::assertSentCount(1);
    }

    public function test_uncertain_refund_errors_preserve_reserved_funds(): void
    {
        $payment = $this->createPayment();
        $this->webhook($payment)->assertOk();
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->assignRole('finance-admin');
        $responses = [
            Http::response(['error' => ['type' => 'api_error']], 500),
            Http::response(['error' => ['type' => 'invalid_request_error', 'code' => 'idempotency_key_in_use']], 400),
            Http::response(['error' => ['type' => 'idempotency_error']], 400),
            Http::response(['error' => ['type' => 'invalid_request_error']], 400, ['Stripe-Should-Retry' => 'true']),
            Http::response(['error' => ['type' => 'invalid_request_error']], 429),
            Http::response('Untrusted gateway response', 400),
            Http::response(['error' => ['type' => 'invalid_request_error']], 401),
        ];
        foreach ($responses as $response) {
            Http::fake(['https://api.stripe.com/v1/refunds' => $response]);
            try {
                app(RefundPayment::class)->execute($admin, $payment->fresh(), 4000, 'uncertain-refund');
                $this->fail('Expected an uncertain provider failure.');
            } catch (DomainException $exception) {
                $this->assertSame('PROVIDER_UNAVAILABLE', $exception->errorCode()->value);
            }
            $this->assertSame(6000, Wallet::findOrFail($payment->wallet_id)->balance);
            $this->assertDatabaseHas('payment_refunds', ['payment_id' => $payment->id, 'status' => 'pending']);
            $this->assertDatabaseCount('wallet_transactions', 2);
            Http::assertSentCount(1);
        }
    }

    public function test_refund_webhook_without_a_provider_reference_cannot_release_funds(): void
    {
        $payment = $this->createPayment();
        $this->webhook($payment)->assertOk();
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->assignRole('finance-admin');
        Http::fake(['https://api.stripe.com/v1/refunds' => Http::response(['id' => 're_pending', 'status' => 'pending'])]);
        $refund = app(RefundPayment::class)->execute($admin, $payment->fresh(), 4000, 'pending-refund');

        foreach ([null, ''] as $reference) {
            try {
                app(ProcessWebhook::class)->applyVerifiedEvent('stripe', ['id' => 'invalid-refund-event', 'type' => 'refund.updated', 'refund_uuid' => $refund->uuid, 'payment_reference' => 'pi_test', 'reference' => $reference, 'amount' => 4000, 'currency' => 'EGP', 'status' => 'failed']);
                $this->fail('Expected an invalid webhook.');
            } catch (DomainException $exception) {
                $this->assertSame('INVALID_WEBHOOK', $exception->errorCode()->value);
            }
        }

        $this->assertSame('pending', $refund->fresh()->status);
        $this->assertSame(6000, Wallet::findOrFail($payment->wallet_id)->balance);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseCount('payment_webhook_events', 1);
        Http::assertSentCount(1);
    }
}
