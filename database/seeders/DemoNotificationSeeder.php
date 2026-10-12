<?php

namespace Database\Seeders;

use App\Models\NotificationDelivery;
use App\Models\SupportConversation;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Str;

/** Historical inbox fixtures, already published; no deliverable outbox work is scheduled. */
final class DemoNotificationSeeder extends DevelopmentSeeder
{
    protected function seed(): void
    {
        foreach (['admin', 'client', 'vendor', 'support', 'finance'] as $key) {
            $user = User::query()->lockForUpdate()->findOrFail($this->user($key)->id);
            foreach (['unread', 'read', 'archived'] as $state) {
                $event = 'demo:notification:'.$key.':'.$state;
                if (UserNotification::query()->where('event_key', $event)->exists()) {
                    continue;
                }
                $version = $user->notifications_version + 1;
                $conversation = $key === 'client' ? SupportConversation::query()->where('user_id', $user->id)->where('status', 'in_progress')->first() : null;
                $notification = UserNotification::query()->create(['id' => (string) Str::uuid(), 'user_id' => $user->id,
                    'event_key' => $event, 'translation_key' => $conversation ? 'notifications.support_message' : 'notifications.new_login',
                    'parameters' => [], 'locale' => $user->preferredLocale(), 'category' => $conversation ? 'transactional' : 'security',
                    'action' => $conversation ? ['screen' => 'support_conversation', 'conversation_id' => $conversation->id, 'last_message_id' => $conversation->last_message_id] : null,
                    'sequence' => $version, 'revision' => $version, 'published_at' => now(),
                    'read_at' => $state === 'read' ? now() : null, 'archived_at' => $state === 'archived' ? now() : null]);
                $values = ['notifications_version' => $version];
                if (! $user->notifications_dirty && $user->notifications_published_version === $version - 1) {
                    $values['notifications_published_version'] = $version;
                }
                $user->forceFill($values)->save();
                if ($state === 'unread') {
                    NotificationDelivery::query()->create(['notification_id' => $notification->id, 'channel' => 'email',
                        'recipient_key' => (string) $user->id, 'status' => 'skipped', 'attempts' => 0]);
                }
            }
        }
    }
}
