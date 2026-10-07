<?php

namespace Tests\Feature\Providers;

use App\Exceptions\DomainException;
use App\Services\Auth\TwilioSms;
use App\Services\Payments\StripeGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StripeGatewayTest extends TestCase
{
    public function test_expired_signature_and_wrong_mode_are_rejected(): void
    {
        $gateway = new StripeGateway(['webhook_secret' => 'test', 'livemode' => true]);
        foreach ([now()->timestamp - 301, now()->timestamp] as $time) {
            $body = json_encode(['id' => 'event', 'type' => 'other', 'livemode' => false, 'data' => ['object' => []]]);
            $sig = 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, 'test');
            try {
                $gateway->verifyWebhook($body, ['stripe-signature' => [$sig]]);
                $this->fail('Expected rejected signature or mode');
            } catch (DomainException $e) {
                $this->assertSame('INVALID_WEBHOOK', $e->errorCode()->value);
            }
        }
    }

    public function test_twilio_sends_canonical_number_and_localized_body_without_logging(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.twilio.com/2010-04-01/Accounts/ACtest/Messages.json' => Http::response(['sid' => 'SMtest'], 201)]);
        (new TwilioSms(['account_sid' => 'ACtest', 'token' => 'secret', 'from' => '+15005550006']))->send('+201012345678', 'رمز التحقق 654321');
        Http::assertSent(fn ($r) => $r['To'] === '+201012345678' && $r['Body'] === 'رمز التحقق 654321');
    }

    public function test_twilio_failure_uses_safe_domain_error(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.twilio.com/*' => Http::response(['message' => 'secret provider details'], 500)]);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('PROVIDER_UNAVAILABLE');
        (new TwilioSms(['account_sid' => 'ACtest', 'token' => 'secret', 'from' => '+15005550006']))->send('+201012345678', '654321');
    }
}
