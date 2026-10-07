<?php
namespace App\Services\Auth;
use App\Enums\{ErrorCode, OtpPurpose, UserType};
use App\Exceptions\DomainException;
use App\Models\OtpChallenge;
use Illuminate\Support\Facades\{DB, Hash, RateLimiter};
use Illuminate\Support\Str;

final class OtpService {
 public function __construct(private readonly SmsManager $sms) {}
 public function issue(string $phone, int $countryId, UserType $type, OtpPurpose $purpose, ?int $userId, string $locale): string {
  $provider = $this->sms->provider();
  $scope = hash('sha256', implode('|',[$phone,$type->value,$purpose->value,$userId]));
  $rateKey = 'otp:phone:'.hash('sha256',$phone);
  if (RateLimiter::tooManyAttempts($rateKey,(int) config('otp.hourly_limit'))) { throw new DomainException(ErrorCode::RATE_LIMITED); }
  RateLimiter::hit($rateKey,3600);
  $code = (string) random_int(100000,999999);
  $id = (string) Str::uuid();
  DB::transaction(function () use ($scope,$code,$id,$phone,$countryId,$type,$purpose,$userId): void {
   OtpChallenge::query()->insertOrIgnore(['scope'=>$scope,'challenge_id'=>$id,'phone'=>$phone,'country_id'=>$countryId,'actor_type'=>$type->value,'purpose'=>$purpose->value,'user_id'=>$userId,'code_hash'=>'','attempts'=>0,'sent_at'=>now()->subDay(),'expires_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
   $row = OtpChallenge::query()->where('scope',$scope)->lockForUpdate()->firstOrFail();
   if ($row->sent_at->addSeconds((int) config('otp.cooldown_seconds'))->isFuture()) { throw new DomainException(ErrorCode::OTP_COOLDOWN); }
   $row->forceFill(['challenge_id'=>$id,'code_hash'=>Hash::make($code),'attempts'=>0,'sent_at'=>now(),'expires_at'=>now()->addSeconds((int) config('otp.ttl_seconds')),'consumed_at'=>null])->save();
  },5);
  try { $provider->send($phone,trans('otp.sms',['code'=>$code],$locale)); }
  catch (\Throwable) {
   OtpChallenge::query()->where('challenge_id',$id)->update(['consumed_at'=>now()]);
   throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
  }
  return $id;
 }
 /** The callback and consumption commit together; invalid attempts commit before throwing. */
 public function consume(string $id, string $phone, UserType $type, OtpPurpose $purpose, ?int $userId, #[\SensitiveParameter] string $code, callable $operation): mixed {
  $result = DB::transaction(function () use ($id,$phone,$type,$purpose,$userId,$code,$operation): mixed {
   $row = OtpChallenge::query()->where('challenge_id',$id)->lockForUpdate()->first();
   if (!$row || $row->phone !== $phone || $row->actor_type !== $type->value || $row->purpose !== $purpose->value || $row->user_id !== $userId) { return new DomainException(ErrorCode::INVALID_OTP); }
   if ($row->consumed_at) { return new DomainException(ErrorCode::OTP_CONSUMED); }
   if ($row->expires_at->lessThanOrEqualTo(now())) { return new DomainException(ErrorCode::OTP_EXPIRED); }
   if ($row->attempts >= (int) config('otp.max_attempts')) { return new DomainException(ErrorCode::OTP_ATTEMPTS_EXCEEDED); }
   $row->increment('attempts');
   if (!Hash::check($code,$row->code_hash)) { return new DomainException(ErrorCode::INVALID_OTP); }
   $value = $operation($row);
   $row->forceFill(['consumed_at'=>now()])->save();
   return $value;
  },5);
  if ($result instanceof DomainException) { throw $result; }
  return $result;
 }
}
