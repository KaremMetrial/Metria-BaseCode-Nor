<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Fixtures\RecordingSms;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_creation_is_repeatable_and_summary_is_read_only(): void
    {
        $user = User::factory()->client()->create(['profile_completed_at' => now(), 'terms_accepted_at' => now(), 'terms_version' => '1']);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/client/referrals')->assertOk()->assertJsonPath('data.code', null)->assertJsonPath('data.rewards_enabled', false);
        $this->assertDatabaseCount('referral_codes', 0);
        $first = $this->postJson('/api/v1/client/referrals/link')->assertOk()->json('data');
        $this->postJson('/api/v1/client/referrals/link')->assertOk()->assertJsonPath('data', $first);
        $this->getJson('/api/v1/client/referrals')->assertOk()->assertJsonPath('data.code', $first['referral_code'])->assertJsonPath('data.share_url', $first['url']);
        $this->deleteJson('/api/v1/client/share-links/'.$first['code'])->assertOk();
        $replacement = $this->postJson('/api/v1/client/referrals/link')->assertOk()->json('data');
        $this->assertNotSame($first['code'], $replacement['code']);
        $this->assertSame($first['referral_code'], $replacement['referral_code']);
        $this->assertDatabaseCount('referral_codes', 1);
    }

    public function test_first_claim_wins_retries_are_safe_and_no_rewards_are_issued(): void
    {
        $this->freezeTime();
        $code = ReferralCode::factory()->create();
        $other = ReferralCode::factory()->create();
        $user = User::factory()->client()->create(['referral_claim_expires_at' => now()->addDay()]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/client/referrals')->assertOk()->assertJsonPath('data.can_claim', true);
        $claim = $this->postJson('/api/v1/client/referrals/claim', ['referral_code' => strtolower($code->code)])->assertOk()->assertJsonPath('data.status', 'registered')->json('data');
        $this->postJson('/api/v1/client/referrals/claim', ['referral_code' => $code->code])->assertOk()->assertJsonPath('data', $claim);
        $this->postJson('/api/v1/client/referrals/claim', ['referral_code' => $other->code])->assertConflict()->assertJsonPath('code', 'RESOURCE_CONFLICT');
        $user->forceFill(['profile_completed_at' => now(), 'referral_claim_expires_at' => now()->subDay()])->save();
        $this->postJson('/api/v1/client/referrals/claim', ['referral_code' => $code->code])->assertOk()->assertJsonPath('data', $claim);
        $this->assertDatabaseCount('referrals', 1);
        $this->assertDatabaseHas('referrals', ['referrer_id' => $code->user_id, 'referred_user_id' => $user->id]);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('user_notifications', 0);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    #[TestWith(['legacy'])]
    #[TestWith(['expired'])]
    #[TestWith(['completed'])]
    public function test_old_completed_and_expired_accounts_cannot_claim(string $state): void
    {
        $this->freezeTime();
        $code = ReferralCode::factory()->create();
        $attributes = match ($state) {
            'legacy' => [],
            'expired' => ['referral_claim_expires_at' => now()],
            'completed' => ['referral_claim_expires_at' => now()->addDay(), 'profile_completed_at' => now()],
        };
        Sanctum::actingAs(User::factory()->client()->create($attributes));

        $this->postJson('/api/v1/client/referrals/claim', ['referral_code' => $code->code])->assertUnprocessable()->assertJsonPath('errors.referral_code.0', __('links.ineligible_referral'));
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_self_invalid_and_disabled_referrers_are_rejected(): void
    {
        $user = User::factory()->client()->create(['referral_claim_expires_at' => now()->addDay()]);
        $self = ReferralCode::factory()->create(['user_id' => $user->id]);
        $disabled = ReferralCode::factory()->create(['user_id' => User::factory()->client()->suspended()->create()->id]);
        Sanctum::actingAs($user);
        foreach ([$self->code, $disabled->code, 'FFFFFFFFFFFFFFFF'] as $code) {
            $this->postJson('/api/v1/client/referrals/claim', ['referral_code' => $code])->assertUnprocessable()->assertJsonPath('errors.referral_code.0', __('links.invalid_referral'));
        }
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_claim_requires_active_verified_client_and_has_rate_limit(): void
    {
        $code = ReferralCode::factory()->create()->code;
        $this->postJson('/api/v1/client/referrals/claim', ['referral_code' => $code])->assertUnauthorized();
        foreach ([User::factory()->admin()->create(), User::factory()->vendor()->create(), User::factory()->client()->suspended()->create(), User::factory()->client()->create(['phone_verified_at' => null])] as $user) {
            Sanctum::actingAs($user);
            $this->postJson('/api/v1/client/referrals/claim', ['referral_code' => $code])->assertForbidden();
        }
        Sanctum::actingAs(User::factory()->client()->create(['referral_claim_expires_at' => now()->addDay()]));
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/client/referrals/claim', ['referral_code' => 'FFFFFFFFFFFFFFFF'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/client/referrals/claim', ['referral_code' => $code])->assertTooManyRequests()->assertHeader('Retry-After');
    }

    public function test_referral_is_atomic_with_profile_completion_and_invalid_code_can_be_omitted(): void
    {
        $code = ReferralCode::factory()->create();
        $user = User::factory()->client()->create(['referral_claim_expires_at' => now()->addDay()]);
        Sanctum::actingAs($user);
        $profile = ['name' => 'New client', 'terms_version' => '1', 'terms_accepted' => true];
        $this->postJson('/api/v1/client/auth/complete-profile', $profile + ['referral_code' => 'FFFFFFFFFFFFFFFF'])->assertUnprocessable();
        $this->assertNull($user->fresh()->profile_completed_at);
        $this->assertDatabaseCount('terms_acceptances', 0);
        $this->postJson('/api/v1/client/auth/complete-profile', $profile + ['referral_code' => $code->code])->assertOk();
        $this->postJson('/api/v1/client/auth/complete-profile', $profile + ['referral_code' => $code->code])->assertOk();
        $this->assertDatabaseCount('referrals', 1);
        $this->assertNotNull($user->fresh()->profile_completed_at);
        Sanctum::actingAs(User::factory()->client()->create(['referral_claim_expires_at' => now()->addDay()]));
        $this->postJson('/api/v1/client/auth/complete-profile', $profile)->assertOk();
        $this->assertDatabaseCount('referrals', 1);
    }

    public function test_history_is_scoped_and_never_exposes_invitee_identity(): void
    {
        $referral = Referral::factory()->create();
        Referral::factory()->create();
        Sanctum::actingAs(User::query()->findOrFail($referral->referrer_id));

        $response = $this->getJson('/api/v1/client/referrals/history')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $referral->id);
        $this->assertSame(['id', 'status', 'created_at'], array_keys($response->json('data.items.0')));
        $this->getJson('/api/v1/client/referrals')->assertOk()->assertJsonPath('data.registered_count', 1)->assertJsonPath('data.rewards_enabled', false);
    }

    public function test_real_otp_registration_opens_claim_window_once_and_profile_consumes_invitation(): void
    {
        $this->freezeTime();
        config(['sms.default' => 'recording', 'sms.providers.recording' => ['driver' => RecordingSms::class], 'access.rate_limits.otp_request' => 50, 'access.rate_limits.otp_verify' => 50]);
        RecordingSms::$messages = [];
        $country = Country::factory()->withIso2('EG')->create();
        $phone = ['country_id' => $country->id, 'phone' => '01012345678'];
        $code = ReferralCode::factory()->create();
        $challenge = $this->postJson('/api/v1/client/auth/otp/request', $phone)->assertOk()->json('data.challenge_id');
        $login = $this->postJson('/api/v1/client/auth/otp/verify', $phone + ['challenge_id' => $challenge, 'code' => RecordingSms::code()])->assertOk()->json('data');
        $user = User::query()->findOrFail($login['user']['id']);
        $this->assertSame(now()->addDay()->toDateTimeString(), $user->referral_claim_expires_at->toDateTimeString());
        $this->withToken($login['token'])->postJson('/api/v1/client/auth/complete-profile', ['name' => 'Invited client', 'terms_accepted' => true, 'terms_version' => '1', 'referral_code' => $code->code])->assertOk();
        $this->travel(2)->days();
        $challenge = $this->postJson('/api/v1/client/auth/otp/request', $phone)->assertOk()->json('data.challenge_id');
        $this->postJson('/api/v1/client/auth/otp/verify', $phone + ['challenge_id' => $challenge, 'code' => RecordingSms::code()])->assertOk();
        $this->assertTrue($user->fresh()->referral_claim_expires_at->equalTo($user->referral_claim_expires_at));
        $this->assertDatabaseCount('referrals', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}
