<?php

namespace Tests\Feature\Finance;

use App\Actions\Payments\CreatePayment;
use App\Actions\Payments\ReconcilePayment;
use App\Actions\Payments\RefundPayment;
use App\Enums\PaymentStatus;
use App\Exceptions\DomainException;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Payments\StripeGateway;
use Database\Seeders\RbacSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function payment(bool $ambiguous = false): Payment
    {
        config(['payments.providers.stripe' => ['driver' => StripeGateway::class, 'secret' => 'test-key', 'webhook_secret' => 'test-webhook', 'livemode' => false]]);
        Http::preventStrayRequests();
        Http::fake(['https://api.stripe.com/v1/payment_intents' => $ambiguous ? Http::failedConnection() : Http::response(['id' => 'pi_reconcile', 'amount' => 1000, 'currency' => 'egp', 'client_secret' => 'test-secret'])]);
        try {
            app(CreatePayment::class)->execute(User::factory()->create(), 1000, 'EGP', 'stripe', 'payment');
        } catch (DomainException $exception) {
            if (! $ambiguous) {
                throw $exception;
            }
        }
        $payment = Payment::query()->sole();
        $payment->forceFill(['created_at' => now()->subDays(2)])->save();

        return $payment;
    }

    private function snapshot(Payment $payment, array $overrides = []): array
    {
        return array_replace(['id' => 'pi_reconcile', 'amount' => 1000, 'amount_received' => 1000, 'currency' => 'egp', 'livemode' => false, 'status' => 'succeeded', 'metadata' => ['payment_uuid' => $payment->uuid]], $overrides);
    }

    public function test_lost_callback_is_recovered_once_by_authenticated_get_without_new_charge(): void
    {
        $payment = $this->payment();
        Http::fake(['https://api.stripe.com/v1/payment_intents/pi_reconcile' => Http::response($this->snapshot($payment))]);

        $this->artisan('payments:reconcile')->assertSuccessful();
        app(ReconcilePayment::class)->execute($payment->fresh());

        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);
        $this->assertSame(1000, Wallet::findOrFail($payment->wallet_id)->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('user_notifications', 1);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_aged_unknown_reference_is_recovered_by_uuid_search_without_recreating_charge(): void
    {
        $payment = $this->payment(true);
        Http::fake(['https://api.stripe.com/v1/payment_intents/search*' => Http::response(['data' => [$this->snapshot($payment)], 'has_more' => false])]);

        $this->artisan('payments:reconcile', ['--payment' => $payment->id])->assertSuccessful();

        $this->assertSame('pi_reconcile', $payment->fresh()->provider_reference);
        $this->assertSame(1000, Wallet::findOrFail($payment->wallet_id)->balance);
        Http::assertSent(fn ($request) => $request->method() === 'GET' && str_contains($request['query'], $payment->uuid));
    }

    public function test_missing_search_result_preserves_ambiguous_intent_and_fails_command(): void
    {
        $payment = $this->payment(true);
        Http::fake(['https://api.stripe.com/v1/payment_intents/search*' => Http::response(['data' => [], 'has_more' => false])]);

        $this->artisan('payments:reconcile')->assertFailed();

        $this->assertSame(PaymentStatus::PENDING, $payment->fresh()->status);
        $this->assertSame(0, Wallet::findOrFail($payment->wallet_id)->balance);
        $this->assertDatabaseCount('wallet_transactions', 0);
        Http::assertSentCount(1);
    }

    public function test_untrusted_mode_amount_or_metadata_never_settles_a_payment(): void
    {
        $payment = $this->payment();
        foreach ([['livemode' => true], ['amount' => 999], ['metadata' => ['payment_uuid' => 'wrong']], ['id' => 'pi_wrong']] as $invalid) {
            Http::fake(['https://api.stripe.com/v1/payment_intents/pi_reconcile' => Http::response($this->snapshot($payment, $invalid))]);
            $this->artisan('payments:reconcile')->assertFailed();
            Http::assertSentCount(1);
            $this->assertSame(PaymentStatus::PENDING, $payment->fresh()->status);
            $this->assertDatabaseCount('wallet_transactions', 0);
        }
    }

    public function test_provider_failure_never_changes_balance_or_leaks_response_body(): void
    {
        $payment = $this->payment();
        Http::fake(['https://api.stripe.com/v1/payment_intents/pi_reconcile' => Http::response('secret-provider-diagnostic', 503)]);

        $this->artisan('payments:reconcile')->doesntExpectOutputToContain('secret-provider-diagnostic')->assertFailed();

        $this->assertSame(PaymentStatus::PENDING, $payment->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
        Http::assertSentCount(1);
    }

    public function test_failed_refund_recovery_releases_reserved_funds_once(): void
    {
        $payment = $this->payment();
        Http::fake(['https://api.stripe.com/v1/payment_intents/pi_reconcile' => Http::response($this->snapshot($payment))]);
        app(ReconcilePayment::class)->execute($payment);
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->assignRole('finance-admin');
        Http::fake(['https://api.stripe.com/v1/refunds' => Http::failedConnection()]);
        try {
            app(RefundPayment::class)->execute($admin, $payment->fresh(), 400, 'refund');
            $this->fail('Expected provider timeout.');
        } catch (DomainException $exception) {
            $this->assertSame('PROVIDER_UNAVAILABLE', $exception->errorCode()->value);
        }
        $refund = PaymentRefund::query()->sole();
        $this->assertSame(600, Wallet::findOrFail($payment->wallet_id)->balance);
        Http::fake(['https://api.stripe.com/v1/refunds*' => Http::response(['has_more' => false, 'data' => [['id' => 're_recovered', 'payment_intent' => 'pi_reconcile', 'amount' => 400, 'currency' => 'egp', 'status' => 'failed', 'livemode' => false, 'metadata' => ['refund_uuid' => $refund->uuid]]]])]);

        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame('failed', $refund->fresh()->status);
        $this->assertSame(1000, Wallet::findOrFail($payment->wallet_id)->balance);
        $this->assertDatabaseCount('wallet_transactions', 3);
        Http::assertSentCount(1);
    }

    public function test_command_rejects_unbounded_options(): void
    {
        Http::preventStrayRequests();
        $this->artisan('payments:reconcile', ['--limit' => 10000])->assertExitCode(2);
        Http::assertNothingSent();
    }

    public function test_successful_refund_reconciliation_keeps_the_original_reservation_debit(): void
    {
        $payment = $this->payment();
        Http::fake(['https://api.stripe.com/v1/payment_intents/pi_reconcile' => Http::response($this->snapshot($payment))]);
        app(ReconcilePayment::class)->execute($payment);
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->assignRole('finance-admin');
        Http::fake(['https://api.stripe.com/v1/refunds' => Http::response(['id' => 're_pending', 'status' => 'pending'])]);
        $refund = app(RefundPayment::class)->execute($admin, $payment->fresh(), 400, 'refund');
        Http::fake(['https://api.stripe.com/v1/refunds/re_pending' => Http::response(['id' => 're_pending', 'payment_intent' => 'pi_reconcile', 'amount' => 400, 'currency' => 'egp', 'status' => 'succeeded', 'livemode' => false, 'metadata' => ['refund_uuid' => $refund->uuid]])]);

        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame('succeeded', $refund->fresh()->status);
        $this->assertSame(PaymentStatus::PARTIALLY_REFUNDED, $payment->fresh()->status);
        $this->assertSame(400, $payment->fresh()->refunded_amount);
        $this->assertSame(600, Wallet::findOrFail($payment->wallet_id)->balance);
        $this->assertDatabaseCount('wallet_transactions', 2);
        Http::assertSentCount(1);
    }

    public function test_reconciliation_rotates_pending_refunds_and_continues_after_provider_failure(): void
    {
        $this->freezeTime();
        $payment = $this->payment();
        Http::fake(['https://api.stripe.com/v1/payment_intents/pi_reconcile' => Http::response($this->snapshot($payment))]);
        app(ReconcilePayment::class)->execute($payment);
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->assignRole('finance-admin');
        Http::fake(['https://api.stripe.com/v1/refunds' => fn ($request) => Http::response(['id' => 're_'.$request['metadata']['refund_uuid'], 'status' => 'pending'])]);
        $refunds = [];
        for ($index = 0; $index < 26; $index++) {
            $refunds[] = app(RefundPayment::class)->execute($admin, $payment->fresh(), 1, 'refund-'.$index);
        }
        Http::assertSentCount(26);
        $first = $refunds[0];
        $last = $refunds[25];
        Http::fake(['https://api.stripe.com/v1/refunds/*' => function ($request) use ($first, $last): PromiseInterface {
            $reference = basename($request->url());
            if ($reference === $first->provider_reference) {
                return Http::response(['error' => ['type' => 'api_error']], 503);
            }
            $refund = PaymentRefund::query()->where('provider_reference', $reference)->firstOrFail();

            return Http::response(['id' => $reference, 'payment_intent' => 'pi_reconcile', 'amount' => 1, 'currency' => 'egp', 'livemode' => false, 'status' => $refund->id === $last->id ? 'succeeded' : 'pending', 'metadata' => ['refund_uuid' => $refund->uuid]]);
        }]);
        $this->travel(1)->minutes();

        $firstRun = app(ReconcilePayment::class)->execute($payment->fresh());
        $this->travel(1)->minutes();
        $secondRun = app(ReconcilePayment::class)->execute($payment->fresh());

        $this->assertSame(['payment_id' => $payment->id, 'settled' => 0, 'unresolved' => 25], $firstRun);
        $this->assertSame(['payment_id' => $payment->id, 'settled' => 1, 'unresolved' => 24], $secondRun);
        $this->assertSame('succeeded', $last->fresh()->status);
        $this->assertSame('pending', $first->fresh()->status);
        $this->assertSame(1, $payment->fresh()->refunded_amount);
        $this->assertSame(974, Wallet::findOrFail($payment->wallet_id)->balance);
        Http::assertSentCount(50);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/refunds/'.$last->provider_reference);
    }
}
