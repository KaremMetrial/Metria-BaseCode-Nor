<?php
namespace App\Services\Realtime;
use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\User;
final class SocketTokenService {
 public function issue(User $user): array {
  $secret=(string)config('realtime.secret');
  if (strlen($secret)<32) { throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE); }
  if (!$user->isActive()) { throw new DomainException(ErrorCode::ACCOUNT_DISABLED); }
  $expiry=now()->timestamp+min(300,(int)config('realtime.ttl_seconds'));
  $claims=['sub'=>(string)$user->id,'type'=>$user->type->value,'aud'=>'metrial-socket','iat'=>now()->timestamp,'exp'=>$expiry];
  $payload=rtrim(strtr(base64_encode(json_encode($claims,JSON_THROW_ON_ERROR)),'+/','-_'),'=');
  return ['token'=>$payload.'.'.hash_hmac('sha256',$payload,$secret),'expires_at'=>$expiry];
 }
}
