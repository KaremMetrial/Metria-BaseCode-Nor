<?php
namespace App\Services\Payments;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\{Payment,PaymentRefund};
use Illuminate\Support\Facades\Http;
final class StripeGateway implements PaymentGatewayInterface {
 public function __construct(private readonly array $settings) {}
 public function create(Payment $payment): array {
  $data=$this->post('payment_intents',['amount'=>$payment->amount,'currency'=>strtolower($payment->currency),'metadata'=>['payment_uuid'=>$payment->uuid],'automatic_payment_methods'=>['enabled'=>'true']],'payment:'.$payment->uuid);
  if (!is_string($data['id'] ?? null) || !is_string($data['client_secret'] ?? null) || ($data['amount'] ?? null)!==$payment->amount || strtoupper($data['currency'] ?? '')!==$payment->currency) { throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE); }
  return ['reference'=>$data['id'],'client_secret'=>$data['client_secret']];
 }
 public function refund(Payment $payment,PaymentRefund $refund): array {
  $data=$this->post('refunds',['payment_intent'=>$payment->provider_reference,'amount'=>$refund->amount,'metadata'=>['refund_uuid'=>$refund->uuid]],'refund:'.$refund->uuid);
  if (!is_string($data['id'] ?? null) || !in_array($data['status'] ?? '',['succeeded','pending','failed','canceled','requires_action'],true)) { throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE); }
  return ['reference'=>$data['id'],'status'=>match($data['status']) {'succeeded'=>'succeeded','failed','canceled'=>'failed',default=>'pending'}];
 }
 private function post(string $path,array $payload,string $key): array {
  if (empty($this->settings['secret'])) { throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE); }
  try {
   $response=Http::asForm()->withToken($this->settings['secret'])->withHeaders(['Idempotency-Key'=>$key])->connectTimeout(3)->timeout(15)->post('https://api.stripe.com/v1/'.$path,$payload);
   if (!$response->successful() || !is_array($response->json())) { throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE); }
   return $response->json();
  } catch(\Throwable) { throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE); }
 }
 public function verifyWebhook(string $body,string $signature): array {
  $secret=$this->settings['webhook_secret'] ?? '';
  if ($secret==='') { throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE); }
  $parts=[];
  foreach(explode(',',$signature) as $part) { $pair=explode('=',trim($part),2); if(count($pair)===2) { $parts[$pair[0]][]=$pair[1]; } }
  $timestamp=$parts['t'][0] ?? '';
  if (!ctype_digit($timestamp) || abs(now()->timestamp-(int)$timestamp)>300) { throw new DomainException(ErrorCode::INVALID_WEBHOOK); }
  $expected=hash_hmac('sha256',$timestamp.'.'.$body,$secret);
  $valid=false;
  foreach($parts['v1'] ?? [] as $value) { if(hash_equals($expected,$value)) { $valid=true; } }
  if(!$valid) { throw new DomainException(ErrorCode::INVALID_WEBHOOK); }
  try { $data=json_decode($body,true,512,JSON_THROW_ON_ERROR); } catch(\JsonException) { throw new DomainException(ErrorCode::INVALID_WEBHOOK); }
  if (!is_array($data) || !is_string($data['id'] ?? null) || !is_string($data['type'] ?? null) || !is_array($data['data']['object'] ?? null) || ($data['livemode'] ?? null)!==($this->settings['livemode'] ?? true)) { throw new DomainException(ErrorCode::INVALID_WEBHOOK); }
  return ['id'=>$data['id'],'type'=>$data['type'],'object'=>$data['data']['object']];
 }
}
