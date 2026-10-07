<?php
namespace App\Services\Auth;
use App\Contracts\Sms\SmsProviderInterface;
use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\Http;
final class TwilioSms implements SmsProviderInterface {
 public function __construct(private readonly array $settings) {}
 public function send(string $e164, #[\SensitiveParameter] string $message): void {
  if (empty($this->settings['account_sid']) || empty($this->settings['token']) || empty($this->settings['from'])) { throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE); }
  try {
   $response = Http::asForm()->withBasicAuth($this->settings['account_sid'], $this->settings['token'])->connectTimeout(3)->timeout(10)
    ->post('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($this->settings['account_sid']).'/Messages.json', ['To'=>$e164,'From'=>$this->settings['from'],'Body'=>$message]);
   if (!$response->successful() || !is_string($response->json('sid'))) { throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE); }
  } catch (\Throwable) { throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE); }
 }
}
