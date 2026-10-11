<?php

namespace App\Services\Notifications;

use App\Enums\ErrorCode;
use App\Enums\UserType;
use App\Exceptions\DomainException;
use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class NotificationOutbox
{
    public function record(User $user, string $key, array $parameters, string $eventKey, string $category = 'transactional', ?array $action = null): void
    {
        ksort($parameters);
        if ($action !== null) {
            ksort($action);
        }
        DB::transaction(function () use ($user, $key, $parameters, $eventKey, $category, $action): void {
            if (! in_array($category, ['security', 'transactional', 'marketing'], true)) {
                throw new DomainException(ErrorCode::VALIDATION_FAILED);
            }
            $user = User::withTrashed()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $inserted = UserNotification::query()->insertOrIgnore(['category' => $category, 'action' => $action === null ? null : json_encode($action, JSON_THROW_ON_ERROR), 'sequence' => $user->notifications_version + 1, 'revision' => $user->notifications_version + 1, 'id' => (string) Str::uuid(), 'user_id' => $user->id, 'event_key' => $eventKey, 'translation_key' => $key, 'parameters' => json_encode($parameters, JSON_THROW_ON_ERROR), 'locale' => $user->preferredLocale(), 'created_at' => now(), 'updated_at' => now()]);
            $notification = UserNotification::query()->where('event_key', $eventKey)->firstOrFail();
            $storedParameters = $notification->parameters;
            ksort($storedParameters);
            $storedAction = $notification->action;
            if ($storedAction !== null) {
                ksort($storedAction);
            }
            if ($notification->user_id !== $user->id || $notification->translation_key !== $key || $storedParameters !== $parameters || $notification->category !== $category || $storedAction !== $action) {
                throw new DomainException(ErrorCode::IDEMPOTENCY_CONFLICT);
            }
            if (! $inserted) {
                return;
            }
            $user->increment('notifications_version', 1, ['notifications_dirty' => true]);
            $targets = [];
            if ($user->type !== UserType::CLIENT && config('notifications.email_enabled') && $user->email_verified_at && $user->email) {
                $targets[] = ['email', (string) $user->id];
            }
            if (config('notifications.fcm_enabled') && $user->notificationPreferences()[$category.'_push']) {
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
