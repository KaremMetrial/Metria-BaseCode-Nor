<?php

namespace Tests\Feature\Notifications;

use App\Jobs\PublishNotification;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Predis\Client;
use Tests\TestCase;

class RedisQueueIntegrationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        if (! getenv('RUN_REDIS_INTEGRATION')) {
            $this->markTestSkipped('Requires isolated Redis; see audit verification commands.');
        }
    }

    public function test_real_redis_queue_worker_publishes_durable_notification(): void
    {
        config(['queue.default' => 'redis', 'queue.connections.redis.queue' => 'metrial-audit-notifications', 'cache.default' => 'redis']);
        $cache = Cache::store('redis');
        $cache->put('metrial:audit:roundtrip', 'ok', 30);
        $this->assertSame('ok', $cache->get('metrial:audit:roundtrip'));
        $cache->forget('metrial:audit:roundtrip');
        $user = User::factory()->create(['locale' => 'ar']);
        app(NotificationOutbox::class)->record($user, 'notifications.phone_changed', [], 'integration:redis');
        $row = UserNotification::firstOrFail();
        Queue::connection('redis')->push(new PublishNotification($row->id));
        $this->assertSame(1, Queue::connection('redis')->size('metrial-audit-notifications'));
        Artisan::call('queue:work', ['connection' => 'redis', '--queue' => 'metrial-audit-notifications', '--once' => true, '--tries' => 1, '--timeout' => 20]);
        $this->assertSame(0, Queue::connection('redis')->size('metrial-audit-notifications'));
        $this->assertNotNull($row->fresh()->published_at);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_overlapping_workers_publish_only_one_message(): void
    {
        app(NotificationOutbox::class)->record(User::factory()->create(), 'notifications.phone_changed', [], 'integration:overlap');
        $row = UserNotification::query()->sole();
        $subscriber = $this->subscriber($row);
        $dispatcher = UserNotification::getEventDispatcher();
        UserNotification::setEventDispatcher(clone $dispatcher);
        $overlapped = false;
        UserNotification::updating(function () use ($row, &$overlapped): void {
            if (! $overlapped) {
                $overlapped = true;
                (new PublishNotification($row->id))->handle();
            }
        });

        try {
            (new PublishNotification($row->id))->handle();

            $this->assertTrue($overlapped);
            $this->assertNotNull($row->fresh()->published_at);
            $this->assertSingleMessage($subscriber, $row);
        } finally {
            UserNotification::setEventDispatcher($dispatcher);
            $subscriber->disconnect();
        }
    }

    public function test_retry_after_database_failure_does_not_republish(): void
    {
        app(NotificationOutbox::class)->record(User::factory()->create(), 'notifications.phone_changed', [], 'integration:sql-failure');
        $row = UserNotification::query()->sole();
        $subscriber = $this->subscriber($row);
        $dispatcher = UserNotification::getEventDispatcher();
        UserNotification::setEventDispatcher(clone $dispatcher);
        $failSave = true;
        UserNotification::updating(function () use (&$failSave): void {
            if ($failSave) {
                $failSave = false;
                throw new \RuntimeException('database unavailable');
            }
        });

        try {
            try {
                (new PublishNotification($row->id))->handle();
                $this->fail('Expected database failure.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('database unavailable', $exception->getMessage());
            }
            $this->assertNull($row->fresh()->published_at);
            $this->assertSame(-1, Redis::connection()->ttl('notification-published:'.$row->id));

            (new PublishNotification($row->id))->handle();

            $this->assertNotNull($row->fresh()->published_at);
            $this->assertGreaterThan(0, Redis::connection()->ttl('notification-published:'.$row->id));
            $this->assertSingleMessage($subscriber, $row);
        } finally {
            UserNotification::setEventDispatcher($dispatcher);
            $subscriber->disconnect();
        }
    }

    private function subscriber(UserNotification $notification): Client
    {
        $connection = config('database.redis.'.config('broadcasting.connections.redis.connection', 'default'));
        $subscriber = new Client([
            'host' => $connection['host'], 'port' => $connection['port'],
            'username' => $connection['username'], 'password' => $connection['password'],
            'database' => $connection['database'], 'read_write_timeout' => 2,
        ]);
        $subscriber->executeRaw(['SUBSCRIBE', config('database.redis.options.prefix').'private-user.'.$notification->user_id]);

        return $subscriber;
    }

    private function assertSingleMessage(Client $subscriber, UserNotification $notification): void
    {
        $message = $subscriber->getConnection()->read();
        $this->assertSame('message', $message[0]);
        $this->assertSame($notification->id, json_decode($message[2], true, 512, JSON_THROW_ON_ERROR)['data']['id']);
        $this->assertSame(['pong', 'done'], $subscriber->executeRaw(['PING', 'done']));
    }
}
