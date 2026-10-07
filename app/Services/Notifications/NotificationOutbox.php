<?php
namespace App\Services\Notifications;
use App\Models\{User,UserNotification};
use Illuminate\Support\Str;
final class NotificationOutbox {
 public function record(User $user,string $key,array $parameters,string $eventKey): void {
  UserNotification::query()->insertOrIgnore(['id'=>(string)Str::uuid(),'user_id'=>$user->id,'event_key'=>$eventKey,'translation_key'=>$key,'parameters'=>json_encode($parameters,JSON_THROW_ON_ERROR),'locale'=>$user->preferredLocale(),'created_at'=>now(),'updated_at'=>now()]);
 }
}
