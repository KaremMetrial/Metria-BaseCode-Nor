<?php

namespace Tests\Feature\Security;

use App\Services\Operations\ReadinessChecks;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
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
