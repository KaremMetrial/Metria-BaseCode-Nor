<?php

namespace App\Jobs;

use App\Models\UserNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

final class PublishNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 20;

    public array $backoff = [5, 30, 120, 300];

    public function __construct(public readonly string $notificationId)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $row = UserNotification::query()->find($this->notificationId);
        if (! $row || $row->published_at) {
            return;
        }
        Redis::connection(config('broadcasting.connections.redis.connection', 'default'))->publish('private-user.'.$row->user_id, json_encode(['event' => 'notification.created', 'data' => $row->payload()], JSON_THROW_ON_ERROR));
        $row->forceFill(['published_at' => now()])->save();
    }
}
