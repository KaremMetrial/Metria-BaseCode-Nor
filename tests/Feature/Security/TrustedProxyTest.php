<?php

namespace Tests\Feature\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_trusted_proxy_keeps_client_rate_limits_separate(): void
    {
        config(['trustedproxy.proxies' => ['172.20.0.1'], 'access.rate_limits.otp_request' => 1]);
        Route::middleware(['api', 'throttle:otp_request'])->post('/api/_test/proxy', fn (Request $request) => response()->json(['ip' => $request->ip(), 'secure' => $request->secure()]));
        $this->withServerVariables(['REMOTE_ADDR' => '172.20.0.1']);

        $this->postJson('/api/_test/proxy', [], ['X-Forwarded-For' => '198.51.100.10', 'X-Forwarded-Proto' => 'https'])
            ->assertOk()->assertJson(['ip' => '198.51.100.10', 'secure' => true]);
        $this->postJson('/api/_test/proxy', [], ['X-Forwarded-For' => '198.51.100.20'])->assertOk()->assertJsonPath('ip', '198.51.100.20');
        $this->postJson('/api/_test/proxy', [], ['X-Forwarded-For' => '198.51.100.10'])->assertTooManyRequests();
    }

    public function test_untrusted_client_cannot_spoof_forwarded_headers_to_bypass_rate_limits(): void
    {
        config(['trustedproxy.proxies' => ['172.20.0.1'], 'access.rate_limits.otp_request' => 1]);
        Route::middleware(['api', 'throttle:otp_request'])->post('/api/_test/proxy', fn (Request $request) => response()->json(['ip' => $request->ip(), 'secure' => $request->secure()]));
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.30']);

        $this->postJson('/api/_test/proxy', [], ['X-Forwarded-For' => '198.51.100.10', 'X-Forwarded-Proto' => 'https'])
            ->assertOk()->assertJson(['ip' => '198.51.100.30', 'secure' => false]);
        $this->postJson('/api/_test/proxy', [], ['X-Forwarded-For' => '198.51.100.20'])->assertTooManyRequests();
    }
}
