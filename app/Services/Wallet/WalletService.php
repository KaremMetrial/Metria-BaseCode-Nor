<?php
namespace App\Services\Wallet;
use App\Enums\{ErrorCode,WalletTransactionType};
use App\Exceptions\DomainException;
use App\Models\{User,Wallet,WalletTransaction};
use Illuminate\Support\Facades\DB;
final class WalletService {
 public function forUser(User $user,string $currency): Wallet {
  $this->currency($currency);
  return DB::transaction(function() use($user,$currency): Wallet {
   User::withTrashed()->whereKey($user->id)->lockForUpdate()->firstOrFail();
   $wallet=Wallet::query()->where('user_id',$user->id)->where('currency',$currency)->first();
   if (!$wallet) { $wallet=new Wallet; $wallet->forceFill(['user_id'=>$user->id,'currency'=>$currency,'balance'=>0,'is_locked'=>false])->save(); }
   return $wallet;
  },5);
 }
 public function change(Wallet $wallet,WalletTransactionType $direction,int $amount,string $key,string $reason): WalletTransaction {
  $this->amount($amount);
  if ($key==='' || strlen($key)>128 || $reason==='' || mb_strlen($reason)>255) { throw new DomainException(ErrorCode::VALIDATION_FAILED); }
  $hash=hash('sha256',json_encode([$direction->value,$amount,$wallet->currency,$reason],JSON_THROW_ON_ERROR));
  return DB::transaction(function() use($wallet,$direction,$amount,$key,$reason,$hash): WalletTransaction {
   $locked=Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
   $previous=WalletTransaction::query()->where('wallet_id',$locked->id)->where('idempotency_key',$key)->first();
   if ($previous) {
    if (!hash_equals($previous->request_hash,$hash)) { throw new DomainException(ErrorCode::IDEMPOTENCY_CONFLICT); }
    return $previous;
   }
   if ($locked->is_locked) { throw new DomainException(ErrorCode::WALLET_LOCKED); }
   $credit=$direction===WalletTransactionType::CREDIT;
   if (!$credit && $locked->balance<$amount) { throw new DomainException(ErrorCode::INSUFFICIENT_BALANCE); }
   if ($credit && $locked->balance>(int)config('payments.max_balance')-$amount) { throw new DomainException(ErrorCode::INVALID_AMOUNT); }
   $balance=$credit ? $locked->balance+$amount : $locked->balance-$amount;
   // Conditional update also protects the invariant on SQLite, where FOR UPDATE is unavailable.
   $changed=Wallet::query()->whereKey($locked->id)->where('balance',$locked->balance)->update(['balance'=>$balance]);
   if ($changed!==1) { throw new DomainException(ErrorCode::IDEMPOTENCY_CONFLICT); }
   $entry=new WalletTransaction;
   $entry->forceFill(['wallet_id'=>$locked->id,'direction'=>$direction,'amount'=>$amount,'balance_after'=>$balance,'idempotency_key'=>$key,'request_hash'=>$hash,'reason'=>$reason,'created_at'=>now()])->save();
   return $entry;
  },5);
 }
 public function amount(int $amount): void { if ($amount<=0 || $amount>(int)config('payments.max_amount')) { throw new DomainException(ErrorCode::INVALID_AMOUNT); } }
 public function currency(string $currency): void { if (!in_array($currency,config('payments.currencies'),true)) { throw new DomainException(ErrorCode::CURRENCY_MISMATCH); } }
}
