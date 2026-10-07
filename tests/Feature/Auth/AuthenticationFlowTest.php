<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Country;
use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\RecordingSms;
use Tests\TestCase;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    private function phone(): array
    {
        config(['sms.default' => 'recording', 'sms.providers.recording' => ['driver' => RecordingSms::class], 'access.rate_limits.otp_request' => 50, 'access.rate_limits.otp_verify' => 50]);
        RecordingSms::$messages = [];
        $country = Country::factory()->withIso2('EG')->create();

        return ['country_id' => $country->id, 'phone' => '01012345678'];
    }

    private function challenge(array $data, string $actor = 'client'): array
    {
        $id = $this->postJson('/api/v1/'.$actor.'/auth/otp/request', $data)->assertOk()->json('data.challenge_id');

        return $data + ['challenge_id' => $id, 'code' => RecordingSms::code()];
    }

    public function test_admin_login_issues_token_and_logout_revokes_it(): void
    {
        $user = User::factory()->admin()->create();
        $response = $this->postJson('/api/v1/admin/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->withToken($response->json('data.token'))->postJson('/api/v1/admin/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.login', 'subject_id' => $user->id]);
    }

    public static function rejectedAdmins(): array
    {
        return [['admin', 'active', 'wrong'], ['client', 'active', 'password'], ['vendor', 'active', 'password'], ['admin', 'pending', 'password'], ['admin', 'blocked', 'password'], ['admin', 'suspended', 'password'], ['admin', 'inactive', 'password']];
    }

    #[DataProvider('rejectedAdmins')]
    public function test_invalid_admin_credentials_do_not_issue_tokens(string $type, string $status, string $password): void
    {
        $user = User::factory()->create(['type' => UserType::from($type), 'status' => UserStatus::from($status)]);
        $this->postJson('/api/v1/admin/auth/login', ['email' => $user->email, 'password' => $password])->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_login_is_rate_limited_and_unknown_email_is_generic(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/admin/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->postJson('/api/v1/admin/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertTooManyRequests()->assertHeader('Retry-After');
    }

    public function test_phone_login_persists_e164_hashed_code_and_prevents_replay(): void
    {
        $data = $this->challenge($this->phone());
        $row = OtpChallenge::query()->firstOrFail();
        $this->assertNotSame($data['code'], $row->code_hash);
        $this->assertTrue(Hash::check($data['code'], $row->code_hash));
        $data['phone'] = '+201012345678';
        $this->postJson('/api/v1/client/auth/otp/verify', $data)->assertOk()->assertJsonPath('data.user.phone', '+201012345678');
        $this->postJson('/api/v1/client/auth/otp/verify', $data)->assertUnprocessable()->assertJsonPath('code', 'OTP_CONSUMED');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_vendor_is_pending_and_cannot_access_client_or_active_surface(): void
    {
        $data = $this->challenge($this->phone(), 'vendor');
        $r = $this->postJson('/api/v1/vendor/auth/otp/verify', $data)->assertOk()->assertJsonPath('data.user.status', 'pending');
        $this->withToken($r->json('data.token'))->getJson('/api/v1/vendor/profile')->assertOk();
        $this->getJson('/api/v1/vendor/wallet')->assertForbidden()->assertJsonPath('code', 'ACCOUNT_PENDING');
        $this->getJson('/api/v1/client/profile')->assertForbidden();
    }

    public function test_wrong_actor_and_wrong_purpose_cannot_consume_code(): void
    {
        $data = $this->challenge($this->phone());
        $this->postJson('/api/v1/vendor/auth/otp/verify', $data)->assertUnprocessable()->assertJsonPath('code', 'INVALID_OTP');
        Sanctum::actingAs(User::factory()->client()->create());
        $this->postJson('/api/v1/client/profile/phone/verify', $data)->assertUnprocessable()->assertJsonPath('code', 'INVALID_OTP');
        $this->assertNull(OtpChallenge::query()->first()->consumed_at);
    }

    public function test_expired_code_is_rejected_at_expiry_boundary(): void
    {
        $this->freezeTime();
        $data = $this->challenge($this->phone());
        $this->travel(300)->seconds();
        $this->postJson('/api/v1/client/auth/otp/verify', $data)->assertUnprocessable()->assertJsonPath('code', 'OTP_EXPIRED');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_failed_attempts_persist_and_exhausted_code_cannot_authenticate(): void
    {
        $data = $this->challenge($this->phone());
        $wrong = $data;
        $wrong['code'] = '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/client/auth/otp/verify', $wrong)->assertUnprocessable();
        }
        $this->postJson('/api/v1/client/auth/otp/verify', $data)->assertTooManyRequests()->assertJsonPath('code', 'OTP_ATTEMPTS_EXCEEDED');
        $this->assertSame(5, OtpChallenge::query()->first()->attempts);
    }

    public function test_resend_cooldown_and_superseded_challenge(): void
    {
        $this->freezeTime();
        $phone = $this->phone();
        $old = $this->challenge($phone);
        $this->postJson('/api/v1/client/auth/otp/request', $phone)->assertTooManyRequests()->assertJsonPath('code', 'OTP_COOLDOWN');
        $this->travel(61)->seconds();
        $new = $this->challenge($phone);
        $this->postJson('/api/v1/client/auth/otp/verify', $old)->assertUnprocessable()->assertJsonPath('code', 'INVALID_OTP');
        $this->postJson('/api/v1/client/auth/otp/verify', $new)->assertOk();
    }

    public function test_disabled_country_and_fixed_line_are_rejected(): void
    {
        $phone = $this->phone();
        Country::query()->update(['is_active' => false]);
        $this->postJson('/api/v1/client/auth/otp/request', $phone)->assertUnprocessable()->assertJsonPath('code', 'UNSUPPORTED_PHONE_COUNTRY');
        Country::query()->update(['is_active' => true]);
        $phone['phone'] = '+20223456789';
        $this->postJson('/api/v1/client/auth/otp/request', $phone)->assertUnprocessable()->assertJsonPath('code', 'PHONE_TYPE_NOT_ALLOWED');
        $this->assertCount(0, RecordingSms::$messages);
    }

    public function test_phone_change_updates_verified_identity_revokes_tokens_and_records_audit(): void
    {
        $phone = $this->phone();
        $user = User::factory()->client()->create();
        $user->createToken('old');
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/v1/client/profile/phone/request', $phone)->assertOk()->json('data.challenge_id');
        $this->postJson('/api/v1/client/profile/phone/verify', $phone + ['challenge_id' => $id, 'code' => RecordingSms::code()])->assertOk();
        $this->assertSame('+201012345678', $user->fresh()->phone);
        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.phone_changed']);
        $this->assertDatabaseCount('user_notifications', 1);
    }

    public function test_profile_rejects_privilege_and_identity_mass_assignment(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/client/profile', ['type' => 'admin', 'phone' => '+201012345678', 'balance' => 100])->assertUnprocessable();
        $this->assertSame(UserType::CLIENT, $user->fresh()->type);
        $this->patchJson('/api/v1/client/profile', ['name' => 'Updated', 'locale' => 'ar'])->assertOk();
        $this->assertSame('Updated', $user->fresh()->name);
    }

    public function test_unconfigured_sms_never_claims_delivery(): void
    {
        $phone = $this->phone();
        config(['sms.default' => 'disabled']);
        $this->postJson('/api/v1/client/auth/otp/request',$phone)->assertServiceUnavailable();
        $this->assertDatabaseCount('otp_challenges',0);
    }
}
