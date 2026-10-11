<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\DB;
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
        if (! User::query()->whereKey($row->user_id)->where('status', 'active')->exists()) {
            $row->forceFill(['published_at' => now()])->save();

            return;
        }
        $redis = Redis::connection(config('broadcasting.connections.redis.connection', 'default'));
        $marker = 'notification-published:'.$row->id;
        $script = <<<'LUA'
            if redis.call('exists', KEYS[1]) == 1 then
                return 0
            end
            redis.call('set', KEYS[1], '1')
            local result = redis.pcall('publish', KEYS[2], ARGV[1])
            if type(result) == 'table' and result.err then
                redis.call('del', KEYS[1])
                return redis.error_reply(result.err)
            end
            return 1
            LUA;
        $arguments = [$marker, 'private-user.'.$row->user_id, json_encode(['event' => 'notification.created', 'data' => $row->payload()], JSON_THROW_ON_ERROR)];
        if ($redis instanceof PhpRedisConnection) {
            $redis->eval($script, 2, ...$arguments);
        } else {
            $redis->command('eval', [$script, 2, ...$arguments]);
        }
        $row->forceFill(['published_at' => now()])->save();

        /** Keep the marker until SQL commits, then retain it beyond the worker timeout for in-flight duplicates. */
        DB::afterCommit(fn () => $redis->expire($marker, 86400));
    }
}
