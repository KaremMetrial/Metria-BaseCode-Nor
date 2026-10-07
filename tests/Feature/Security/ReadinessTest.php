<?php

namespace Tests\Feature\Security;

use App\Services\Operations\ReadinessChecks;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
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
}
