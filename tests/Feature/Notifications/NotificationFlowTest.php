<?php

namespace Tests\Feature\Notifications;

use App\Jobs\PublishNotification;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_locale_ownership_and_idempotent_delivery(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);
        $outbox = app(NotificationOutbox::class);
        $outbox->record($user, 'notifications.phone_changed', [], 'event:one');
        $outbox->record($user, 'notifications.phone_changed', [], 'event:one');
        $row = UserNotification::firstOrFail();
        $this->assertDatabaseCount('user_notifications', 1);
        app()->setLocale('en');
        $this->assertSame('تم تغيير رقم هاتفك.', $row->payload()['body']);
        Sanctum::actingAs(User::factory()->create());
        $this->patchJson('/api/v1/client/notifications/'.$row->id.'/read')->assertNotFound();
        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/client/notifications/'.$row->id.'/read')->assertOk();
        $this->assertNotNull($row->fresh()->read_at);
        Redis::shouldReceive('connection')->once()->andReturnSelf();
        Redis::shouldReceive('publish')->once()->with('private-user.'.$user->id, \Mockery::on(fn ($s) => json_decode($s, true)['data']['body'] === 'تم تغيير رقم هاتفك.'))->andReturn(1);
        $job = new PublishNotification($row->id);
        $job->handle();
        $job->handle();
        $this->assertNotNull($row->fresh()->published_at);
    }

    public function test_redis_failure_leaves_durable_notification_pending(): void
    {
        $user = User::factory()->create();
        app(NotificationOutbox::class)->record($user, 'notifications.phone_changed', [], 'event:failure');
        $row = UserNotification::firstOrFail();
        Redis::shouldReceive('connection')->once()->andReturnSelf();
        Redis::shouldReceive('publish')->once()->andThrow(new \RuntimeException('unavailable'));
        try {
            (new PublishNotification($row->id))->handle();
            $this->fail('Expected Redis failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('unavailable', $e->getMessage());
        }
        $this->assertNull($row->fresh()->published_at);
    }
}
