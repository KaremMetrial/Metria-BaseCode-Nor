<?php
namespace App\Actions\Payments;
use App\Enums\{ErrorCode,PaymentStatus};
use App\Exceptions\DomainException;
use App\Models\{Payment,User};
use App\Services\Payments\GatewayManager;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
final class CreatePayment {
 public function __construct(private readonly GatewayManager $gateways,private readonly WalletService $wallets) {}
 public function execute(User $user,int $amount,string $currency,string $provider,string $key): Payment {
  $this->wallets->amount($amount); $this->wallets->currency($currency);
  $gateway=$this->gateways->get($provider);
  $hash=hash('sha256',json_encode([$amount,$currency,$provider],JSON_THROW_ON_ERROR));
  $payment=DB::transaction(function() use($user,$amount,$currency,$provider,$key,$hash): Payment {
   User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
   $existing=Payment::query()->where('user_id',$user->id)->where('idempotency_key',$key)->first();
   if($existing) { if(!hash_equals($existing->request_hash,$hash)) { throw new DomainException(ErrorCode::IDEMPOTENCY_CONFLICT); } return $existing; }
   $wallet=$this->wallets->forUser($user,$currency);
   $payment=new Payment;
   $payment->forceFill(['uuid'=>(string)Str::uuid(),'user_id'=>$user->id,'wallet_id'=>$wallet->id,'provider'=>$provider,'amount'=>$amount,'currency'=>$currency,'status'=>PaymentStatus::PENDING,'refunded_amount'=>0,'idempotency_key'=>$key,'request_hash'=>$hash])->save();
   return $payment;
  },5);
  if($payment->provider_reference!==null) { return $payment; }
  // Never recreate an ambiguous charge after the provider's idempotency retention window.
  if($payment->created_at->lt(now()->subHours(23))) { throw new DomainException(ErrorCode::RECONCILIATION_REQUIRED); }
  $result=$gateway->create($payment);
  return DB::transaction(function() use($payment,$result): Payment {
   $locked=Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
   if($locked->provider_reference!==null && $locked->provider_reference!==$result['reference']) { throw new DomainException(ErrorCode::IDEMPOTENCY_CONFLICT); }
   $locked->forceFill(['provider_reference'=>$result['reference'],'client_secret'=>$result['client_secret']])->save();
   return $locked;
  },5);
 }
}
