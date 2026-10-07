<?php
namespace App\Services\Auth;
use App\Contracts\Sms\SmsProviderInterface;
use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
final class SmsManager {
 public function provider(?string $name = null): SmsProviderInterface {
  $name ??= config('sms.default');
  $settings = config('sms.providers')[$name] ?? null;
  if (!is_array($settings) || !is_a($settings['driver'] ?? '', SmsProviderInterface::class, true)) { throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE); }
  return app()->make($settings['driver'], ['settings' => $settings]);
 }
}
