<?php

namespace App\Services\Notifications;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Mail\AccountNotificationMail;
use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

final class DeliverNotification
{
    public function __construct(private readonly FcmClient $push) {}

    public function execute(int $id): void
    {
        $lock = Cache::lock('notification-delivery:'.$id, 55);
        if (! $lock->get()) {
            return;
        }
        try {
            $delivery = NotificationDelivery::query()->find($id);
            if (! $delivery || $delivery->status !== 'pending' || $delivery->available_at?->isFuture()) {
                return;
            }
            $notification = UserNotification::query()->findOrFail($delivery->notification_id);
            $user = User::query()->find($notification->user_id);
            if (! $user || ! $user->isActive()) {
                $delivery->forceFill(['status' => 'skipped'])->save();

                return;
            }
            $payload = $notification->payload();
            $delivery->increment('attempts');
            try {
                $status = 'sent';
                if ($delivery->channel === 'email') {
                    if (! $user->email_verified_at || ! $user->email) {
                        $status = 'skipped';
                    } elseif (! config('notifications.email_enabled') || in_array(config('mail.default'), ['log', 'array'], true)) {
                        throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
                    } else {
                        Mail::to($user->email)->send(new AccountNotificationMail($payload['title'], $payload['body'], $notification->locale));
                    }
                } elseif ($delivery->channel === 'fcm') {
                    $device = DeviceToken::query()->where('user_id', $user->id)->find($delivery->recipient_key);
                    if (! $device) {
                        $status = 'skipped';
                    } elseif (! config('notifications.fcm_enabled')) {
                        throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
                    } elseif (! $this->push->send($device, $payload)) {
                        $device->delete();
                        $status = 'skipped';
                    }
                } else {
                    throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
                }
                $delivery->forceFill(['status' => $status, 'available_at' => null])->save();
            } catch (\Throwable) {
                $delivery->forceFill(['status' => $delivery->attempts >= 8 ? 'failed' : 'pending', 'available_at' => now()->addMinutes(5)])->save();
                throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
            }
        } finally {
            $lock->release();
        }
    }
}
