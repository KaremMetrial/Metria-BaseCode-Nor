<?php

namespace App\Services\Notifications;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class NotificationOutbox
{
    public function record(User $user, string $key, array $parameters, string $eventKey): void
    {
        DB::transaction(function () use ($user, $key, $parameters, $eventKey): void {
            UserNotification::query()->insertOrIgnore(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'event_key' => $eventKey, 'translation_key' => $key, 'parameters' => json_encode($parameters, JSON_THROW_ON_ERROR), 'locale' => $user->preferredLocale(), 'created_at' => now(), 'updated_at' => now()]);
            $notification = UserNotification::query()->where('event_key', $eventKey)->firstOrFail();
            if ($notification->user_id !== $user->id || $notification->translation_key !== $key || $notification->parameters !== $parameters) {
                throw new DomainException(ErrorCode::IDEMPOTENCY_CONFLICT);
            }
            $targets = [];
            if (config('notifications.email_enabled') && $user->email_verified_at && $user->email) {
                $targets[] = ['email', (string) $user->id];
            }
            if (config('notifications.fcm_enabled')) {
                foreach (DeviceToken::query()->where('user_id', $user->id)->limit(10)->pluck('id') as $id) {
                    $targets[] = ['fcm', (string) $id];
                }
            }
            foreach ($targets as [$channel, $recipient]) {
                NotificationDelivery::query()->insertOrIgnore(['notification_id' => $notification->id, 'channel' => $channel, 'recipient_key' => $recipient, 'status' => 'pending', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }
        }, 5);
    }
}
