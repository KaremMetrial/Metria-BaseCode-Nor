<?php
namespace Tests\Feature\Notifications;
use App\Jobs\PublishNotification;
use App\Models\{User,UserNotification};
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{Artisan,Cache,Queue,Redis};
use Tests\TestCase;
class RedisQueueIntegrationTest extends TestCase {
 use DatabaseMigrations;
 public function test_real_redis_queue_worker_publishes_durable_notification(): void {
  if(!getenv('RUN_REDIS_INTEGRATION')) {$this->markTestSkipped('Requires isolated Redis; see audit verification commands.');}
  config(['queue.default'=>'redis','queue.connections.redis.queue'=>'metrial-audit-notifications','cache.default'=>'redis']);
  $cache=Cache::store('redis');$cache->put('metrial:audit:roundtrip','ok',30);$this->assertSame('ok',$cache->get('metrial:audit:roundtrip'));$cache->forget('metrial:audit:roundtrip');
  $user=User::factory()->create(['locale'=>'ar']);app(NotificationOutbox::class)->record($user,'notifications.phone_changed',[],'integration:redis');
  $row=UserNotification::firstOrFail();Queue::connection('redis')->push(new PublishNotification($row->id));
  $this->assertSame(1,Queue::connection('redis')->size('metrial-audit-notifications'));
  Artisan::call('queue:work',['connection'=>'redis','--queue'=>'metrial-audit-notifications','--once'=>true,'--tries'=>1,'--timeout'=>20]);
  $this->assertSame(0,Queue::connection('redis')->size('metrial-audit-notifications'));$this->assertNotNull($row->fresh()->published_at);
  $this->assertDatabaseCount('failed_jobs',0);
 }
}
