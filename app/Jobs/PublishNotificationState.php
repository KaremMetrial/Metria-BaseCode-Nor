<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

final class PublishNotificationState implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 20;

    public array $backoff = [5, 30, 120, 300];

    public function __construct(public readonly int $userId)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $state = DB::transaction(function (): ?array {
            $user = User::query()->whereKey($this->userId)->lockForUpdate()->first();
            if (! $user || ! $user->isActive() || $user->notifications_version <= $user->notifications_published_version) {
                return null;
            }

            return ['version' => $user->notifications_version, 'unread_count' => UserNotification::query()->where('user_id', $user->id)->whereNull('archived_at')->whereNull('read_at')->count()];
        }, 5);
        if ($state === null) {
            return;
        }
        Redis::connection(config('broadcasting.connections.redis.connection', 'default'))->publish('private-user.'.$this->userId, json_encode(['event' => 'notification.state_changed', 'data' => $state], JSON_THROW_ON_ERROR));
        User::query()->whereKey($this->userId)->where('notifications_published_version', '<', $state['version'])->update(['notifications_published_version' => $state['version']]);
        User::query()->whereKey($this->userId)->where('notifications_version', $state['version'])->update(['notifications_dirty' => false]);
    }
}
