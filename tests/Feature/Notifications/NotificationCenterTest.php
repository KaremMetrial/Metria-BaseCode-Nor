<?php

namespace Tests\Feature\Notifications;

use App\Actions\Shared\Notifications\RegisterDeviceToken;
use App\Exceptions\DomainException;
use App\Jobs\PublishNotification;
use App\Jobs\PublishNotificationState;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\DeliverNotification;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function record(User $user, string $key, string $category = 'transactional'): UserNotification
    {
        app(NotificationOutbox::class)->record($user, 'notifications.phone_changed', [], $key, $category);

        return UserNotification::query()->where('event_key', $key)->sole();
    }

    public function test_inbox_filters_read_state_and_category_without_leaking_other_accounts(): void
    {
        $user = User::factory()->client()->create();
        $security = $this->record($user, 'security', 'security');
        $this->record($user, 'payment');
        $this->record(User::factory()->client()->create(), 'other');
        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/client/notifications/'.$security->id.'/read')->assertOk();
        $this->getJson('/api/v1/client/notifications?status=unread')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.unread_count', 1);
        $this->getJson('/api/v1/client/notifications?category=security')->assertOk()->assertJsonPath('data.items.0.id', $security->id)->assertJsonCount(1, 'data.items');
        $this->getJson('/api/v1/client/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 1);
        $this->getJson('/api/v1/client/notifications?per_page=500')->assertUnprocessable();
    }

    public function test_read_all_preserves_notifications_created_after_snapshot_and_is_idempotent(): void
    {
        $this->freezeTime();
        $user = User::factory()->client()->create();
        $first = $this->record($user, 'first');
        Sanctum::actingAs($user);
        $snapshot = $this->getJson('/api/v1/client/notifications')->assertOk()->json('data.meta.snapshot_version');
        $later = $this->record($user, 'later');
        $this->patchJson('/api/v1/client/notifications/read-all', ['through_version' => $snapshot])->assertOk()->assertJsonPath('data.updated', 1)->assertJsonPath('data.unread_count', 1);
        $readAt = $first->fresh()->read_at->toIso8601String();
        $this->travel(1)->minutes();
        $this->patchJson('/api/v1/client/notifications/read-all', ['through_version' => $snapshot])->assertOk()->assertJsonPath('data.updated', 0);
        $this->assertSame($readAt, $first->fresh()->read_at->toIso8601String());
        $this->assertNull($later->fresh()->read_at);
    }

    public function test_archive_is_owner_scoped_and_synchronization_includes_tombstones(): void
    {
        $user = User::factory()->client()->create();
        $row = $this->record($user, 'archive');
        $version = $user->fresh()->notifications_version;
        Sanctum::actingAs(User::factory()->client()->create());
        $this->patchJson('/api/v1/client/notifications/'.$row->id.'/archive')->assertNotFound();
        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/client/notifications/'.$row->id.'/archive')->assertOk();
        $archivedAt = $row->fresh()->archived_at->toIso8601String();
        $this->patchJson('/api/v1/client/notifications/'.$row->id.'/archive')->assertOk();
        $this->assertSame($archivedAt, $row->fresh()->archived_at->toIso8601String());
        $this->getJson('/api/v1/client/notifications')->assertOk()->assertJsonCount(0, 'data.items')->assertJsonPath('data.unread_count', 0);
        $this->getJson('/api/v1/client/notifications?since_version='.$version)->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.archived_at', $archivedAt);
    }

    public function test_sync_cursor_does_not_skip_records_when_earlier_rows_change_between_pages(): void
    {
        $user = User::factory()->client()->create();
        $first = $this->record($user, 'first');
        $second = $this->record($user, 'second');
        Sanctum::actingAs($user);
        $page = $this->getJson('/api/v1/client/notifications?since_version=0&per_page=1')->assertOk();
        $snapshot = $page->json('data.meta.snapshot_version');
        $cursor = $page->json('data.meta.next_cursor');
        $this->patchJson('/api/v1/client/notifications/'.$first->id.'/read')->assertOk();
        $this->getJson('/api/v1/client/notifications?since_version=0&per_page=1&until_version='.$snapshot.'&cursor='.urlencode($cursor))
            ->assertOk()->assertJsonPath('data.items.0.id', $second->id);
        $this->getJson('/api/v1/client/notifications?since_version='.$snapshot)->assertOk()->assertJsonPath('data.items.0.id', $first->id);
    }

    public function test_marketing_is_opt_in_and_preferences_reject_unknown_keys(): void
    {
        config(['notifications.fcm_enabled' => true]);
        $user = User::factory()->client()->create();
        $token = $user->createToken('push');
        $user->withAccessToken($token->accessToken);
        app(RegisterDeviceToken::class)->execute($user, str_repeat('a', 30));
        $this->withToken($token->plainTextToken);
        $this->getJson('/api/v1/client/notification-preferences')->assertOk()->assertJsonPath('data.preferences.marketing_push', false);
        $this->record($user, 'no-marketing', 'marketing');
        $this->assertDatabaseCount('notification_deliveries', 0);
        $this->patchJson('/api/v1/client/notification-preferences', ['preferences' => ['marketing_push' => true, 'user_id' => 99]])->assertUnprocessable();
        $this->patchJson('/api/v1/client/notification-preferences', ['preferences' => ['marketing_push' => true]])->assertOk()->assertJsonPath('data.preferences.security_push', true);
        $this->record($user, 'marketing', 'marketing');
        $this->assertDatabaseCount('notification_deliveries', 1);
    }

    public function test_pending_push_is_skipped_after_logout_or_preference_change(): void
    {
        config(['notifications.fcm_enabled' => true]);
        Http::preventStrayRequests();
        $user = User::factory()->client()->create();
        $token = $user->createToken('push');
        $user->withAccessToken($token->accessToken);
        app(RegisterDeviceToken::class)->execute($user, str_repeat('a', 30));
        $this->record($user, 'pending');
        $delivery = NotificationDelivery::query()->sole();
        $this->withToken($token->plainTextToken)->postJson('/api/v1/client/auth/logout')->assertOk();
        app(DeliverNotification::class)->execute($delivery->id);
        $this->assertSame('skipped', $delivery->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_disabling_push_rechecks_pending_delivery_and_retains_security_inbox(): void
    {
        config(['notifications.fcm_enabled' => true]);
        Http::preventStrayRequests();
        $user = User::factory()->client()->create();
        $token = $user->createToken('push');
        $user->withAccessToken($token->accessToken);
        app(RegisterDeviceToken::class)->execute($user, str_repeat('a', 30));
        $this->record($user, 'security', 'security');
        $delivery = NotificationDelivery::query()->sole();
        $this->withToken($token->plainTextToken)->patchJson('/api/v1/client/notification-preferences', ['preferences' => ['security_push' => false]])->assertOk();
        app(DeliverNotification::class)->execute($delivery->id);
        $this->assertSame('skipped', $delivery->fresh()->status);
        $this->getJson('/api/v1/client/notifications')->assertOk()->assertJsonCount(1, 'data.items');
        Http::assertNothingSent();
    }

    public function test_client_never_receives_email_even_if_legacy_email_is_verified(): void
    {
        config(['notifications.email_enabled' => true]);
        $user = User::factory()->client()->create();
        $this->record($user, 'client-security', 'security');
        $this->assertDatabaseCount('notification_deliveries', 0);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/client/profile')->assertOk()->assertJsonMissingPath('data.email');
    }

    public function test_state_outbox_survives_publish_failure_and_does_not_lose_newer_changes(): void
    {
        $user = User::factory()->client()->create();
        $this->record($user, 'first');
        Redis::shouldReceive('connection')->andReturnSelf();
        Redis::shouldReceive('publish')->once()->andThrow(new \RuntimeException('unavailable'));
        try {
            (new PublishNotificationState($user->id))->handle();
            $this->fail('Expected publication failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('unavailable', $exception->getMessage());
        }
        $this->assertSame(0, $user->fresh()->notifications_published_version);
        Redis::shouldReceive('publish')->once()->with('private-user.'.$user->id, \Mockery::on(fn ($payload): bool => json_decode($payload, true)['data'] === ['version' => 1, 'unread_count' => 1]))
            ->andReturnUsing(function () use ($user): int {
                $this->record($user, 'arrived-during-publish');

                return 1;
            });
        (new PublishNotificationState($user->id))->handle();
        $this->assertSame(1, $user->fresh()->notifications_published_version);
        $this->assertSame(2, $user->fresh()->notifications_version);
    }

    public function test_rollback_discards_notification_and_state_version(): void
    {
        $user = User::factory()->client()->create();
        DB::beginTransaction();
        $this->record($user, 'rolled-back');
        DB::rollBack();
        $this->assertDatabaseCount('user_notifications', 0);
        $this->assertSame(0, $user->fresh()->notifications_version);
    }

    public function test_duplicate_event_does_not_requeue_push_after_consent_changes(): void
    {
        config(['notifications.fcm_enabled' => true]);
        $user = User::factory()->client()->create();
        $user->withAccessToken($user->createToken('push')->accessToken);
        app(RegisterDeviceToken::class)->execute($user, str_repeat('a', 30));
        $this->record($user, 'marketing', 'marketing');
        $user->forceFill(['notification_preferences' => ['marketing_push' => true]])->save();
        $this->record($user, 'marketing', 'marketing');
        $this->assertDatabaseCount('notification_deliveries', 0);
        $this->assertSame(1, $user->fresh()->notifications_version);
        try {
            $this->record($user, 'marketing', 'security');
            $this->fail('Expected a conflicting event.');
        } catch (DomainException $exception) {
            $this->assertSame('IDEMPOTENCY_CONFLICT', $exception->errorCode()->value);
        }
    }

    public function test_expired_session_cannot_receive_queued_push(): void
    {
        $this->freezeTime();
        config(['notifications.fcm_enabled' => true]);
        Http::preventStrayRequests();
        $user = User::factory()->client()->create();
        $token = $user->createToken('push', ['*'], now()->addMinute());
        $user->withAccessToken($token->accessToken);
        app(RegisterDeviceToken::class)->execute($user, str_repeat('a', 30));
        $this->record($user, 'expiring');
        $delivery = NotificationDelivery::query()->sole();
        $this->travel(1)->minutes();
        app(DeliverNotification::class)->execute($delivery->id);
        $this->assertSame('skipped', $delivery->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_notification_payload_key_order_does_not_change_idempotency(): void
    {
        $user = User::factory()->client()->create();
        $outbox = app(NotificationOutbox::class);
        $outbox->record($user, 'notifications.payment_received', ['amount' => '1.00', 'currency' => 'EGP'], 'payment', 'transactional', ['screen' => 'payments', 'id' => '123']);
        $outbox->record($user, 'notifications.payment_received', ['currency' => 'EGP', 'amount' => '1.00'], 'payment', 'transactional', ['id' => '123', 'screen' => 'payments']);
        $this->assertDatabaseCount('user_notifications', 1);
        $this->assertSame(1, $user->fresh()->notifications_version);
    }

    public function test_closed_account_notifications_are_not_broadcast(): void
    {
        $user = User::factory()->client()->create();
        $row = $this->record($user, 'before-closure');
        $user->delete();
        Redis::shouldReceive('connection')->never();
        (new PublishNotification($row->id))->handle();
        $this->assertNotNull($row->fresh()->published_at);
    }
}
