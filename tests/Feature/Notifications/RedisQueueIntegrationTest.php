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
use Symfony\Component\Process\Process;
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

    public function test_laravel_token_and_queued_notifications_reach_socket_io(): void
    {
        if (! getenv('RUN_SOCKET_INTEGRATION')) {
            $this->markTestSkipped('Requires Node.js and npm ci in socketio, in addition to isolated Redis.');
        }
        config([
            'queue.default' => 'redis', 'queue.connections.redis.queue' => 'metrial-socket-integration',
            'realtime.secret' => 'socket-integration-only-secret-at-least-32-bytes',
            'notifications.email_enabled' => false, 'notifications.fcm_enabled' => false,
        ]);
        $user = User::factory()->create(['locale' => 'ar']);
        $bearer = $user->createToken('socket-integration')->plainTextToken;
        $token = $this->withToken($bearer)->postJson('/api/v1/client/realtime/token')
            ->assertOk()->json('data.token');
        $redis = config('database.redis.'.config('broadcasting.connections.redis.connection', 'default'));
        $port = (string) (getenv('TEST_LARAVEL_SOCKET_PORT') ?: 16096);
        $server = new Process(['node', base_path('socketio/app.js')], base_path('socketio'), [
            'NODE_ENV' => 'test', 'PORT' => $port,
            'SOCKET_TOKEN_SECRET' => config('realtime.secret'),
            'REDIS_HOST' => $redis['host'], 'REDIS_PORT' => (string) $redis['port'],
            'REDIS_PASSWORD' => $redis['password'] ?? '', 'REDIS_DB' => (string) $redis['database'],
            'REDIS_PREFIX' => config('database.redis.options.prefix'),
        ]);
        $client = new Process(['node', base_path('tests/Fixtures/socket-notification-client.cjs')], base_path());
        $client->setInput(json_encode(['origin' => 'http://127.0.0.1:'.$port, 'token' => $token], JSON_THROW_ON_ERROR));
        $client->setTimeout(20);

        try {
            $server->start();
            $client->start();
            $this->assertTrue($client->waitUntil(fn (): bool => str_contains($client->getOutput(), '"ready":true')), $client->getErrorOutput().$server->getErrorOutput());

            app(NotificationOutbox::class)->record($user, 'notifications.phone_changed', [], 'integration:socket-delivery');
            $row = UserNotification::query()->sole();
            $this->assertSame(0, Artisan::call('notifications:publish-pending'));
            $this->assertSame(0, Artisan::call('queue:work', [
                'connection' => 'redis', '--queue' => 'metrial-socket-integration',
                '--stop-when-empty' => true, '--tries' => 1, '--timeout' => 20,
            ]));
            $client->wait();

            $this->assertSame(0, $client->getExitCode(), $client->getErrorOutput());
            $messages = collect(explode("\n", trim($client->getOutput())))
                ->map(fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR))
                ->filter(fn (array $message): bool => isset($message['event']))->keyBy('event');
            $this->assertSame($row->id, $messages['notification.created']['data']['id']);
            $this->assertSame('تم تغيير رقم هاتفك.', $messages['notification.created']['data']['body']);
            $this->assertSame(['version' => 1, 'unread_count' => 1], $messages['notification.state_changed']['data']);
            $this->assertNotNull($row->fresh()->published_at);
            $this->assertDatabaseCount('failed_jobs', 0);
            $this->withToken($bearer)->getJson('/api/v1/client/notifications')
                ->assertOk()->assertJsonPath('data.items.0.id', $row->id)->assertJsonPath('data.unread_count', 1);
        } finally {
            $client->stop();
            $server->stop();
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
