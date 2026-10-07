<?php
namespace App\Actions\Payments;
use App\Enums\{ErrorCode,PaymentStatus,WalletTransactionType};
use App\Exceptions\DomainException;
use App\Models\{Payment,PaymentRefund,PaymentWebhookEvent,Wallet};
use App\Services\Payments\GatewayManager;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
final class ProcessWebhook {
 public function __construct(private readonly GatewayManager $gateways,private readonly WalletService $wallets,private readonly RefundPayment $refunds) {}
 public function execute(string $provider,string $body,string $signature): void {
  $event=$this->gateways->get($provider)->verifyWebhook($body,$signature);
  DB::transaction(function() use($provider,$body,$event): void {
   $inserted=PaymentWebhookEvent::query()->insertOrIgnore(['provider'=>$provider,'provider_event_id'=>$event['id'],'payload_hash'=>hash('sha256',$body),'created_at'=>now()]);
   if(!$inserted) {
    $previous=PaymentWebhookEvent::query()->where('provider',$provider)->where('provider_event_id',$event['id'])->firstOrFail();
    if(!hash_equals($previous->payload_hash,hash('sha256',$body))) { throw new DomainException(ErrorCode::IDEMPOTENCY_CONFLICT); }
    return;
   }
   $object=$event['object'];
   if(in_array($event['type'],['refund.created','refund.updated','refund.failed'],true)) {
    $refund=PaymentRefund::query()->where('uuid',$object['metadata']['refund_uuid'] ?? '')->first();
    if(!$refund) { throw new DomainException(ErrorCode::RECONCILIATION_REQUIRED); }
    $payment=Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
    if($payment->provider!==$provider || $payment->provider_reference!==($object['payment_intent'] ?? null) || $refund->amount!==($object['amount'] ?? null) || $payment->currency!==strtoupper($object['currency'] ?? '')) { throw new DomainException(ErrorCode::INVALID_WEBHOOK); }
    $status=match($object['status'] ?? '') {'succeeded'=>'succeeded','failed','canceled'=>'failed','pending','requires_action'=>'pending',default=>throw new DomainException(ErrorCode::INVALID_WEBHOOK)};
    $this->refunds->settle($refund,$object['id'],$status);
    return;
   }
   if(!in_array($event['type'],['payment_intent.succeeded','payment_intent.canceled'],true)) { return; }
   $payment=Payment::query()->where('uuid',$object['metadata']['payment_uuid'] ?? '')->where('provider',$provider)->lockForUpdate()->first();
   if(!$payment) { throw new DomainException(ErrorCode::INVALID_WEBHOOK); }
   if(!is_string($object['id'] ?? null) || ($payment->provider_reference!==null && $payment->provider_reference!==$object['id']) || $payment->amount!==($object['amount'] ?? null) || $payment->currency!==strtoupper($object['currency'] ?? '')) { throw new DomainException(ErrorCode::INVALID_WEBHOOK); }
   if($event['type']==='payment_intent.succeeded') {
    if(($object['status'] ?? '')!=='succeeded' || ($object['amount_received'] ?? null)!==$payment->amount) { throw new DomainException(ErrorCode::INVALID_WEBHOOK); }
    if(in_array($payment->status,[PaymentStatus::PAID,PaymentStatus::PARTIALLY_REFUNDED,PaymentStatus::REFUNDED],true)) { return; }
    if(!in_array($payment->status,[PaymentStatus::PENDING,PaymentStatus::PROCESSING],true)) { throw new DomainException(ErrorCode::INVALID_PAYMENT_STATUS); }
    $this->wallets->change(Wallet::query()->findOrFail($payment->wallet_id),WalletTransactionType::CREDIT,$payment->amount,'payment:'.$payment->uuid,'payment_received');
    $payment->forceFill(['provider_reference'=>$object['id'],'status'=>PaymentStatus::PAID])->save();
    app(\App\Services\Notifications\NotificationOutbox::class)->record(\App\Models\User::withTrashed()->findOrFail($payment->user_id),'notifications.payment_received',['amount'=>$payment->amount,'currency'=>$payment->currency],'payment:'.$payment->uuid);
    event(new \App\Events\PaymentReceived($payment->id));
   } elseif(in_array($payment->status,[PaymentStatus::PENDING,PaymentStatus::PROCESSING],true)) {
    if(($object['status'] ?? '')!=='canceled') { throw new DomainException(ErrorCode::INVALID_WEBHOOK); }
    $payment->forceFill(['provider_reference'=>$object['id'],'status'=>PaymentStatus::FAILED])->save();
   }
  },5);
 }
}
