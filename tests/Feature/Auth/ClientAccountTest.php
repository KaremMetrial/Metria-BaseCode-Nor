<?php

namespace Tests\Feature\Auth;

use App\Actions\Shared\Notifications\RegisterDeviceToken;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Country;
use App\Models\DeviceToken;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Tests\Fixtures\RecordingSms;
use Tests\TestCase;

class ClientAccountTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateSession(User $user, bool $recent = true): NewAccessToken
    {
        $token = $user->createToken('Test phone', ['*'], now()->addDay());
        $token->accessToken->forceFill(['authenticated_at' => $recent ? now() : now()->subHour()])->save();
        $user->withAccessToken($token->accessToken);
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken);

        return $token;
    }

    private function sms(): Country
    {
        config(['sms.default' => 'recording', 'sms.providers.recording' => ['driver' => RecordingSms::class], 'access.rate_limits.otp_request' => 50, 'access.rate_limits.otp_verify' => 50, 'access.rate_limits.login' => 50]);
        RecordingSms::$messages = [];

        return Country::factory()->withIso2('EG')->create();
    }

    public function test_phone_registration_returns_onboarding_state_and_never_exposes_email(): void
    {
        $country = $this->sms();
        $phone = ['country_id' => $country->id, 'phone' => '01012345678'];
        $challenge = $this->postJson('/api/v1/client/auth/otp/request', $phone)->assertOk()
            ->assertJsonPath('data.expires_in', 300)->assertJsonPath('data.resend_after', 60)->json('data.challenge_id');
        $response = $this->postJson('/api/v1/client/auth/otp/verify', $phone + ['challenge_id' => $challenge, 'code' => RecordingSms::code(), 'device_name' => 'My phone'])
            ->assertOk()->assertJsonPath('data.next_step', 'complete_profile')->assertJsonPath('data.user.profile_completed', false)
            ->assertJsonMissingPath('data.user.email')->assertJsonStructure(['data' => ['expires_at', 'token']]);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'My phone']);
        $this->assertDatabaseHas('user_notifications', ['category' => 'security', 'translation_key' => 'notifications.welcome']);
        $this->withToken($response->json('data.token'))->postJson('/api/v1/client/auth/complete-profile', ['name' => 'Client name', 'terms_version' => '1', 'terms_accepted' => true])
            ->assertOk()->assertJsonPath('data.user.profile_completed', true)->assertJsonPath('data.next_step', 'home');
        $this->assertDatabaseHas('users', ['name' => 'Client name', 'terms_version' => '1', 'email' => null]);
    }

    public function test_registration_completion_requires_current_consent_and_cannot_grant_privileges(): void
    {
        $user = User::factory()->client()->create();
        $this->authenticateSession($user);
        $this->postJson('/api/v1/client/auth/complete-profile', ['name' => 'Client'])->assertUnprocessable();
        $this->postJson('/api/v1/client/auth/complete-profile', ['name' => 'Client', 'terms_version' => 'old', 'terms_accepted' => true])->assertUnprocessable();
        $this->postJson('/api/v1/client/auth/complete-profile', ['name' => 'Client', 'terms_version' => '1', 'terms_accepted' => true, 'type' => 'admin', 'email' => 'client@example.test'])->assertUnprocessable();
        $this->assertNull($user->fresh()->profile_completed_at);
        $data = ['name' => 'Client', 'terms_version' => '1', 'terms_accepted' => true];
        $this->postJson('/api/v1/client/auth/complete-profile', $data)->assertOk();
        $completed = $user->fresh()->profile_completed_at->toIso8601String();
        $accepted = $user->fresh()->terms_accepted_at->toIso8601String();
        $this->travel(1)->minutes();
        $this->postJson('/api/v1/client/auth/complete-profile', $data)->assertOk();
        $this->assertSame($completed, $user->fresh()->profile_completed_at->toIso8601String());
        $this->assertSame($accepted, $user->fresh()->terms_accepted_at->toIso8601String());
        $this->assertSame('client', $user->fresh()->type->value);
    }

    public function test_client_requirements_explain_support_recovery_without_email(): void
    {
        $this->getJson('/api/v1/client/auth/requirements')->assertOk()
            ->assertJsonPath('data.terms_version', '1')->assertJsonPath('data.recovery.method', 'support');
    }

    public function test_sessions_hide_credentials_and_cannot_revoke_another_account(): void
    {
        $user = User::factory()->client()->create();
        $user->createToken('Expired', ['*'], now()->subSecond());
        $current = $this->authenticateSession($user);
        $other = User::factory()->client()->create()->createToken('Other phone');
        $this->getJson('/api/v1/client/auth/sessions')->assertOk()->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.is_current', true)->assertJsonMissingPath('data.items.0.token');
        $this->deleteJson('/api/v1/client/auth/sessions/'.$other->accessToken->id)->assertNotFound();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->deleteJson('/api/v1/client/auth/sessions/'.$current->accessToken->id)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/client/profile')->assertUnauthorized();
    }

    public function test_logout_revokes_only_current_session_and_its_push_subscription(): void
    {
        $user = User::factory()->client()->create();
        $first = $this->authenticateSession($user);
        app(RegisterDeviceToken::class)->execute($user, str_repeat('a', 30));
        $second = $this->authenticateSession($user);
        app(RegisterDeviceToken::class)->execute($user, str_repeat('b', 30));
        $this->postJson('/api/v1/client/auth/logout')->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $first->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $second->accessToken->id]);
        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', ['personal_access_token_id' => $first->accessToken->id]);
    }

    public function test_logout_all_removes_every_session_and_push_subscription(): void
    {
        $user = User::factory()->client()->create();
        $this->authenticateSession($user);
        app(RegisterDeviceToken::class)->execute($user, str_repeat('a', 30));
        $this->authenticateSession($user);
        app(RegisterDeviceToken::class)->execute($user, str_repeat('b', 30));
        $this->postJson('/api/v1/client/auth/logout-all')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_push_token_rotation_replaces_the_current_sessions_old_subscription(): void
    {
        $user = User::factory()->client()->create();
        $current = $this->authenticateSession($user);
        $this->postJson('/api/v1/client/devices', ['token' => str_repeat('a', 30)])->assertCreated();
        $this->postJson('/api/v1/client/devices', ['token' => str_repeat('b', 30)])->assertCreated();
        $this->assertDatabaseCount('device_tokens', 1);
        $device = DeviceToken::query()->sole();
        $this->assertSame(str_repeat('b', 30), $device->token);
        $this->assertSame($current->accessToken->id, $device->personal_access_token_id);
    }

    public function test_phone_change_requires_recent_verification_and_reauth_is_session_scoped(): void
    {
        $country = $this->sms();
        $user = User::factory()->client()->create(['phone' => '+201011111111', 'phone_country_id' => $country->id]);
        $other = $this->authenticateSession($user, false);
        $current = $this->authenticateSession($user, false);
        $phone = ['country_id' => $country->id, 'phone' => '01012345678'];
        $this->postJson('/api/v1/client/profile/phone/request', $phone)->assertForbidden()->assertJsonPath('code', 'REAUTHENTICATION_REQUIRED');
        $id = $this->postJson('/api/v1/client/auth/reauthenticate/request')->assertOk()->json('data.challenge_id');
        $this->assertSame('+201011111111', RecordingSms::$messages[0]['phone']);
        $this->postJson('/api/v1/client/auth/reauthenticate/verify', ['challenge_id' => $id, 'code' => '000000'])->assertUnprocessable();
        $this->postJson('/api/v1/client/auth/reauthenticate/verify', ['challenge_id' => $id, 'code' => RecordingSms::code()])->assertOk();
        $this->assertSame((string) $other->accessToken->authenticated_at, (string) $other->accessToken->fresh()->authenticated_at);
        $this->assertNotSame((string) $current->accessToken->authenticated_at, (string) $current->accessToken->fresh()->authenticated_at);
        $id = $this->postJson('/api/v1/client/profile/phone/request', $phone)->assertOk()->json('data.challenge_id');
        $this->postJson('/api/v1/client/profile/phone/verify', $phone + ['challenge_id' => $id, 'code' => RecordingSms::code()])->assertOk();
        $this->assertSame('+201012345678', $user->fresh()->phone);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_expired_recent_verification_cannot_close_account(): void
    {
        $user = User::factory()->client()->create();
        $this->authenticateSession($user, false);
        $this->deleteJson('/api/v1/client/account', ['confirm' => true])->assertForbidden()->assertJsonPath('code', 'REAUTHENTICATION_REQUIRED');
        $this->assertNotSoftDeleted($user);
    }

    public function test_closure_requires_confirmation_and_preserves_financial_records(): void
    {
        $user = User::factory()->client()->create();
        $this->authenticateSession($user);
        app(RegisterDeviceToken::class)->execute($user, str_repeat('a', 30));
        $wallet = app(WalletService::class)->forUser($user, 'EGP');
        $this->deleteJson('/api/v1/client/account')->assertUnprocessable();
        $this->deleteJson('/api/v1/client/account', ['confirm' => true])->assertOk();
        $this->assertSoftDeleted($user);
        $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'is_locked' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.account_closed', 'subject_id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('device_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/client/profile')->assertUnauthorized();
    }

    public function test_wallet_balance_blocks_closure_without_revoking_access(): void
    {
        $user = User::factory()->client()->create();
        $token = $this->authenticateSession($user);
        $wallets = app(WalletService::class);
        $wallet = $wallets->forUser($user, 'EGP');
        $wallets->change($wallet, WalletTransactionType::CREDIT, 100, 'funding', 'test');
        $this->deleteJson('/api/v1/client/account', ['confirm' => true])->assertConflict()->assertJsonPath('code', 'ACCOUNT_CLOSURE_BLOCKED');
        $this->assertNotSoftDeleted($user);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertSame(100, $wallet->fresh()->balance);
    }

    public function test_avatar_is_private_validated_and_can_be_removed(): void
    {
        Storage::fake('local');
        $user = User::factory()->client()->create();
        $this->authenticateSession($user);
        $this->post('/api/v1/client/profile/avatar', ['avatar' => UploadedFile::fake()->create('unsafe.svg', 1, 'image/svg+xml')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/api/v1/client/profile/avatar', ['avatar' => UploadedFile::fake()->image('portrait.png', 20, 20)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.has_avatar', true)->assertJsonStructure(['data' => ['avatar_url']]);
        Storage::disk('local')->assertExists($user->fresh()->avatar_path);
        $this->deleteJson('/api/v1/client/profile/avatar')->assertOk()->assertJsonPath('data.has_avatar', false);
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_pending_payment_and_refund_each_block_closure(): void
    {
        $user = User::factory()->client()->create();
        $this->authenticateSession($user);
        $wallet = app(WalletService::class)->forUser($user, 'EGP');
        $payment = new Payment;
        $payment->forceFill(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'wallet_id' => $wallet->id, 'provider' => 'stripe', 'amount' => 100, 'currency' => 'EGP', 'status' => PaymentStatus::PENDING, 'refunded_amount' => 0, 'idempotency_key' => 'pending', 'request_hash' => str_repeat('a', 64)])->save();
        $this->deleteJson('/api/v1/client/account', ['confirm' => true])->assertConflict()->assertJsonPath('code', 'ACCOUNT_CLOSURE_BLOCKED');
        $payment->forceFill(['status' => PaymentStatus::PAID])->save();
        $refund = new PaymentRefund;
        $refund->forceFill(['uuid' => (string) Str::uuid(), 'payment_id' => $payment->id, 'actor_id' => User::factory()->admin()->create()->id, 'amount' => 100, 'status' => 'pending', 'idempotency_key' => 'pending-refund', 'request_hash' => str_repeat('b', 64)])->save();
        $this->deleteJson('/api/v1/client/account', ['confirm' => true])->assertConflict()->assertJsonPath('code', 'ACCOUNT_CLOSURE_BLOCKED');
        $this->assertNotSoftDeleted($user);
        $this->assertSame('pending', $refund->fresh()->status);
    }

    public function test_returning_client_logs_in_without_creating_another_account_or_wallet(): void
    {
        $country = $this->sms();
        $user = User::factory()->client()->create(['phone' => '+201012345678', 'phone_country_id' => $country->id, 'profile_completed_at' => now(), 'terms_accepted_at' => now(), 'terms_version' => '1']);
        app(WalletService::class)->forUser($user, 'EGP');
        $deviceId = (string) Str::uuid();
        $old = $user->createToken('Old device');
        $old->accessToken->forceFill(['device_id' => $deviceId])->save();
        $phone = ['country_id' => $country->id, 'phone' => '01012345678'];
        $id = $this->postJson('/api/v1/client/auth/otp/request', $phone)->assertOk()->json('data.challenge_id');
        $this->postJson('/api/v1/client/auth/otp/verify', $phone + ['challenge_id' => $id, 'code' => RecordingSms::code(), 'device_id' => $deviceId])
            ->assertOk()->assertJsonPath('data.next_step', 'home')->assertJsonPath('data.user.id', $user->id);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('wallets', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $old->accessToken->id]);
        $this->assertDatabaseHas('user_notifications', ['translation_key' => 'notifications.new_login']);
    }

    public function test_closed_phone_cannot_be_registered_as_a_new_account(): void
    {
        $country = $this->sms();
        $user = User::factory()->client()->create(['phone' => '+201012345678', 'phone_country_id' => $country->id]);
        $this->authenticateSession($user);
        $this->deleteJson('/api/v1/client/account', ['confirm' => true])->assertOk();
        $this->app['auth']->forgetGuards();
        $phone = ['country_id' => $country->id, 'phone' => '01012345678'];
        $id = $this->postJson('/api/v1/client/auth/otp/request', $phone)->assertOk()->json('data.challenge_id');
        $this->postJson('/api/v1/client/auth/otp/verify', $phone + ['challenge_id' => $id, 'code' => RecordingSms::code()])->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_login_caps_sessions_and_revokes_oldest_push_subscription(): void
    {
        $country = $this->sms();
        config(['client.max_sessions' => 2]);
        $user = User::factory()->client()->create(['phone' => '+201012345678', 'phone_country_id' => $country->id]);
        $first = $this->authenticateSession($user);
        app(RegisterDeviceToken::class)->execute($user, str_repeat('a', 30));
        $second = $this->authenticateSession($user);
        $phone = ['country_id' => $country->id, 'phone' => '01012345678'];
        $id = $this->postJson('/api/v1/client/auth/otp/request', $phone)->assertOk()->json('data.challenge_id');
        $this->postJson('/api/v1/client/auth/otp/verify', $phone + ['challenge_id' => $id, 'code' => RecordingSms::code()])->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $first->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $second->accessToken->id]);
        $this->assertDatabaseCount('device_tokens', 0);
    }
}
