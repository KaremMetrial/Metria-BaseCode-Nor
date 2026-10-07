<?php

namespace Tests\Feature\Security;

use App\Actions\Payments\CreatePayment;
use App\Models\User;
use App\Services\Operations\ReadinessChecks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_sandbox_payments_are_accepted_only_with_the_explicit_staging_option(): void
    {
        config(['payments.default' => 'stripe', 'payments.providers.stripe' => ['secret' => 'sandbox-secret', 'webhook_secret' => 'sandbox-webhook', 'livemode' => false]]);

        $checks = app(ReadinessChecks::class);
        $this->assertFalse($checks->run(false)['payments_configured']);
        $this->assertTrue($checks->run(false, true)['payments_configured']);
        $this->artisan('app:readiness', ['--configuration-only' => true, '--sandbox-payments' => true])
            ->expectsOutputToContain('"payments_configured":true')
            ->doesntExpectOutputToContain('sandbox-secret')
            ->assertFailed();
    }

    public function test_staging_rejects_live_payments_and_missing_payment_mode(): void
    {
        config(['payments.default' => 'stripe', 'payments.providers.stripe' => ['secret' => 'live-secret', 'webhook_secret' => 'live-webhook', 'livemode' => true]]);

        $checks = app(ReadinessChecks::class);
        $this->assertTrue($checks->run(false)['payments_configured']);
        $this->assertFalse($checks->run(false, true)['payments_configured']);

        config(['payments.providers.stripe.livemode' => null]);
        $this->assertFalse($checks->run(false, true)['payments_configured']);
    }

    public function test_development_defaults_cannot_pass_production_readiness(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://localhost', 'payments.default' => 'disabled', 'sms.default' => 'disabled', 'realtime.secret' => '', 'queue.default' => 'sync']);
        $checks = app(ReadinessChecks::class)->run(false);
        foreach (['production_environment', 'debug_disabled', 'https_url', 'payments_configured', 'sms_configured', 'socket_secret', 'redis_queue'] as $name) {
            $this->assertFalse($checks[$name], $name);
        }
        $this->artisan('app:readiness', ['--configuration-only' => true])->assertFailed();
    }

    public function test_readiness_output_never_contains_credentials(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('s', 32)), 'realtime.secret' => 'do-not-print-this-socket-secret-value', 'database.connections.mysql.password' => 'do-not-print-this-password']);
        $this->artisan('app:readiness', ['--configuration-only' => true])->doesntExpectOutputToContain('do-not-print')->assertFailed();
        $checks = app(ReadinessChecks::class)->run(false);
        $this->assertTrue($checks['application_key']);
        $this->assertTrue($checks['socket_secret']);
    }

    public function test_abandoned_payment_remains_an_operational_alert_without_blocking_deployment_checks(): void
    {
        $this->freezeTime();
        config(['payments.providers.stripe.secret' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake(['https://api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_abandoned', 'amount' => 1000, 'currency' => 'egp', 'client_secret' => 'test-secret'])]);
        app(CreatePayment::class)->execute(User::factory()->create(), 1000, 'EGP', 'stripe', 'abandoned');
        $this->travel(2)->days();
        Redis::shouldReceive('connection->ping')->times(3)->andReturn(true);

        $checks = app(ReadinessChecks::class);
        $operational = $checks->run();
        $deployment = $checks->run(deployment: true);

        $this->assertFalse($operational['no_aged_pending_payments']);
        $this->assertArrayNotHasKey('no_aged_pending_payments', $deployment);
        $this->assertTrue($deployment['database_reachable']);
        $this->assertTrue($deployment['redis_reachable']);
        $this->assertArrayHasKey('payments_configured', $deployment);
        $this->artisan('app:readiness', ['--deployment' => true])
            ->expectsOutputToContain('"database_reachable":true')
            ->doesntExpectOutputToContain('no_aged_pending_payments')
            ->assertFailed();
        Http::assertSentCount(1);
    }

    public function test_deployment_checks_still_fail_when_database_is_unavailable(): void
    {
        DB::shouldReceive('select')->once()->with('SELECT 1')->andThrow(new RuntimeException('Connection unavailable'));
        Redis::shouldReceive('connection->ping')->once()->andReturn(true);

        $checks = app(ReadinessChecks::class)->run(deployment: true);

        $this->assertFalse($checks['database_reachable']);
        $this->assertTrue($checks['redis_reachable']);
    }
}
