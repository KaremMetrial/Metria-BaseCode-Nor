<?php

namespace Tests\Feature\Notifications;

use App\Actions\Notifications\RegisterDeviceToken;
use App\Exceptions\DomainException;
use App\Jobs\DeliverNotificationChannel;
use App\Jobs\PublishNotification;
use App\Mail\AccountNotificationMail;
use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Notifications\DeliverNotification;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryChannelsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $credentialsFile = null;

    protected function tearDown(): void
    {
        if ($this->credentialsFile !== null) {
            unlink($this->credentialsFile);
        }
        parent::tearDown();
    }

    private function pushDelivery(): NotificationDelivery
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->credentialsFile = tempnam(sys_get_temp_dir(), 'metrial-fcm-test-');
        chmod($this->credentialsFile, 0600);
        file_put_contents($this->credentialsFile, json_encode(['type' => 'service_account', 'client_email' => 'test@metrial-test.iam.gserviceaccount.com', 'private_key' => $pem, 'project_id' => 'metrial-test'], JSON_THROW_ON_ERROR));
        config(['notifications.fcm_enabled' => true, 'notifications.fcm_project' => 'metrial-test', 'notifications.fcm_credentials' => $this->credentialsFile]);
        $user = User::factory()->create(['locale' => 'ar']);
        app(RegisterDeviceToken::class)->execute($user, 'test-device-token-12345678901234567890');
        app(NotificationOutbox::class)->record($user, 'notifications.phone_changed', [], 'phone-change');
        Http::preventStrayRequests();

        return NotificationDelivery::query()->sole();
    }

    public function test_device_registration_encrypts_secrets_and_cannot_steal_another_users_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $token = 'private-device-token-12345678901234567890';
        $id = $this->postJson('/api/v1/client/devices', ['token' => $token])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/client/devices', ['token' => $token])->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertNotSame($token, DB::table('device_tokens')->value('token'));
        $this->assertArrayNotHasKey('token', DeviceToken::findOrFail($id)->toArray());
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/client/devices', ['token' => $token])->assertConflict();
        $this->deleteJson('/api/v1/client/devices/'.$id)->assertNotFound();
        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/client/devices/'.$id)->assertOk();
        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_verified_email_receives_recipient_locale_once_from_durable_delivery(): void
    {
        config(['notifications.email_enabled' => true, 'mail.default' => 'smtp']);
        $user = User::factory()->create(['locale' => 'ar']);
        $outbox = app(NotificationOutbox::class);
        $outbox->record($user, 'notifications.phone_changed', [], 'email-change');
        $outbox->record($user, 'notifications.phone_changed', [], 'email-change');
        $delivery = NotificationDelivery::query()->sole();
        app()->setLocale('en');
        Mail::fake();

        app(DeliverNotification::class)->execute($delivery->id);
        app(DeliverNotification::class)->execute($delivery->id);

        Mail::assertSent(AccountNotificationMail::class, fn ($mail) => $mail->hasTo($user->email) && $mail->recipientLocale === 'ar' && $mail->notificationBody === 'تم تغيير رقم هاتفك.');
        Mail::assertSentCount(1);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame('en', app()->getLocale());
    }

    public function test_mail_rendering_escapes_content_and_sets_arabic_direction(): void
    {
        $mail = new AccountNotificationMail('تنبيه', '<script>alert(1)</script>', 'ar');
        $mail->assertSeeInHtml('dir="rtl"', false);
        $mail->assertSeeInHtml('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $mail->assertDontSeeInHtml('<script>', false);
    }

    public function test_unverified_email_and_disabled_channels_do_not_enqueue_delivery(): void
    {
        config(['notifications.email_enabled' => true]);
        $user = User::factory()->create(['email_verified_at' => null]);
        app(NotificationOutbox::class)->record($user, 'notifications.phone_changed', [], 'unverified');
        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    public function test_fcm_uses_oauth_and_localized_payload_and_deduplicates_completed_delivery(): void
    {
        $delivery = $this->pushDelivery();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-oauth', 'expires_in' => 3600, 'token_type' => 'Bearer']),
            'https://fcm.googleapis.com/v1/projects/metrial-test/messages:send' => Http::response(['name' => 'projects/metrial-test/messages/one']),
        ]);

        app(DeliverNotification::class)->execute($delivery->id);
        app(DeliverNotification::class)->execute($delivery->id);

        $this->assertSame('sent', $delivery->fresh()->status);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-oauth') && $request['message']['notification']['body'] === 'تم تغيير رقم هاتفك.' && $request['message']['data']['notification_id'] === $delivery->notification_id);
    }

    public function test_provider_confirmed_unregistered_device_is_removed_without_retries(): void
    {
        $delivery = $this->pushDelivery();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-oauth', 'expires_in' => 3600]),
            'https://fcm.googleapis.com/v1/projects/metrial-test/messages:send' => Http::response(['error' => ['details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]], 404),
        ]);

        app(DeliverNotification::class)->execute($delivery->id);

        $this->assertSame('skipped', $delivery->fresh()->status);
        $this->assertDatabaseCount('device_tokens', 0);
        Http::assertSentCount(2);
    }

    public function test_transient_fcm_failure_remains_durable_and_obeys_retry_delay(): void
    {
        $this->freezeTime();
        $delivery = $this->pushDelivery();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-oauth', 'expires_in' => 3600]),
            'https://fcm.googleapis.com/v1/projects/metrial-test/messages:send' => Http::sequence()->push(['error' => 'unavailable'], 503)->push(['name' => 'projects/metrial-test/messages/retried']),
        ]);
        try {
            app(DeliverNotification::class)->execute($delivery->id);
            $this->fail('Expected provider failure.');
        } catch (DomainException $exception) {
            $this->assertSame('PROVIDER_UNAVAILABLE', $exception->errorCode()->value);
        }
        $this->assertSame('pending', $delivery->fresh()->status);
        app(DeliverNotification::class)->execute($delivery->id);
        Http::assertSentCount(2);
        $this->travel(6)->minutes();

        app(DeliverNotification::class)->execute($delivery->id);

        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(2, $delivery->fresh()->attempts);
        Http::assertSentCount(4);
    }

    public function test_outbox_dispatches_channel_jobs_and_transaction_rollback_discards_them(): void
    {
        config(['notifications.email_enabled' => true]);
        $user = User::factory()->create();
        Queue::fake();
        DB::beginTransaction();
        app(NotificationOutbox::class)->record($user, 'notifications.phone_changed', [], 'rolled-back');
        DB::rollBack();
        $this->assertDatabaseCount('notification_deliveries', 0);
        app(NotificationOutbox::class)->record($user, 'notifications.phone_changed', [], 'committed');

        $this->artisan('notifications:publish-pending')->assertSuccessful();

        Queue::assertPushed(DeliverNotificationChannel::class, 1);
        Queue::assertPushed(PublishNotification::class, 1);
    }

    public function test_notification_event_key_cannot_be_reused_for_another_recipient(): void
    {
        $outbox = app(NotificationOutbox::class);
        $outbox->record(User::factory()->create(), 'notifications.phone_changed', [], 'unique-event');
        try {
            $outbox->record(User::factory()->create(), 'notifications.phone_changed', [], 'unique-event');
            $this->fail('Expected recipient conflict.');
        } catch (DomainException $exception) {
            $this->assertSame('IDEMPOTENCY_CONFLICT', $exception->errorCode()->value);
        }
        $this->assertDatabaseCount('user_notifications', 1);
    }
}
