<?php

namespace Tests\Feature\Localization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LocaleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Test routes mounted on the real `api` group, so the assertions cover
        // the production middleware stack rather than a hand-built one.
        Route::middleware('api')->get('/api/_test/locale', fn () => response()->json([
            'locale' => app()->getLocale(),
        ]));

        Route::middleware(['api', 'auth:sanctum'])->get('/api/_test/locale-auth', fn () => response()->json([
            'locale' => app()->getLocale(),
        ]));
    }

    public function test_defaults_to_the_configured_locale(): void
    {
        $this->getJson('/api/_test/locale')
            ->assertOk()
            ->assertJson(['locale' => config('languages.default')]);
    }

    public function test_explicit_query_locale_is_applied(): void
    {
        $this->getJson('/api/_test/locale?locale=ar')
            ->assertOk()
            ->assertJson(['locale' => 'ar']);
    }

    public function test_explicit_header_locale_is_applied(): void
    {
        $this->getJson('/api/_test/locale', ['X-Locale' => 'ar'])
            ->assertOk()
            ->assertJson(['locale' => 'ar']);
    }

    public function test_unsupported_explicit_locale_is_rejected(): void
    {
        $this->getJson('/api/_test/locale?locale=fr')
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'UNSUPPORTED_LOCALE',
            ]);
    }

    public function test_unsupported_explicit_header_locale_is_rejected(): void
    {
        $this->getJson('/api/_test/locale', ['X-Locale' => 'de'])->assertStatus(422);
    }

    public function test_accept_language_is_negotiated(): void
    {
        $this->getJson('/api/_test/locale', ['Accept-Language' => 'ar-EG,ar;q=0.9,en;q=0.8'])
            ->assertOk()
            ->assertJson(['locale' => 'ar']);
    }

    public function test_unsupported_accept_language_falls_back_silently(): void
    {
        // Browsers send this automatically, so a 422 here would break every
        // request from a user whose browser is set to an unsupported language.
        $this->getJson('/api/_test/locale', ['Accept-Language' => 'de-DE,de;q=0.9'])
            ->assertOk()
            ->assertJson(['locale' => config('languages.default')]);
    }

    public function test_user_preference_wins_over_accept_language(): void
    {
        Sanctum::actingAs(User::factory()->client()->create(['locale' => 'ar']));

        $this->getJson('/api/_test/locale-auth', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertOk()
            ->assertJson(['locale' => 'ar']);
    }

    public function test_explicit_request_locale_wins_over_the_user_preference(): void
    {
        Sanctum::actingAs(User::factory()->client()->create(['locale' => 'en']));

        $this->getJson('/api/_test/locale-auth?locale=ar')
            ->assertOk()
            ->assertJson(['locale' => 'ar']);
    }

    public function test_an_unsupported_user_preference_is_ignored_rather_than_rejected(): void
    {
        // A locale saved before it was removed from config must not lock a user
        // out of their own account.
        $user = User::factory()->client()->create();
        $user->forceFill(['locale' => 'zz'])->save();

        Sanctum::actingAs($user);

        $this->getJson('/api/_test/locale-auth')
            ->assertOk()
            ->assertJson(['locale' => config('languages.default')]);
    }

    #[TestWith(['?locale=fr', ['X-Locale' => 'ar']])]
    #[TestWith(['?locale[]=ar', ['Accept-Language' => 'ar-EG']])]
    #[TestWith(['', ['X-Locale' => 'fr', 'Accept-Language' => 'ar']])]
    public function test_unsupported_locale_returns_422_in_the_next_supported_language(string $query, array $headers): void
    {
        $this->getJson('/api/_test/locale'.$query, $headers)
            ->assertUnprocessable()->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('message', 'اللغة المطلوبة غير مدعومة.')
            ->assertJsonPath('errors.locale.0', 'اللغة المطلوبة غير مدعومة.');
    }

    public function test_unsupported_locale_uses_the_authenticated_users_language(): void
    {
        Sanctum::actingAs(User::factory()->client()->create(['locale' => 'ar']));

        $this->getJson('/api/_test/locale-auth?locale=fr', ['Accept-Language' => 'en'])
            ->assertUnprocessable()->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('message', 'اللغة المطلوبة غير مدعومة.');
    }

    public function test_locale_resets_between_requests_and_query_wins_over_header(): void
    {
        $this->getJson('/api/_test/locale?locale=AR', ['X-Locale' => 'en'])
            ->assertOk()->assertHeader('Content-Language', 'ar')->assertJsonPath('locale', 'ar');
        $this->getJson('/api/_test/locale')
            ->assertOk()->assertHeader('Content-Language', 'en')->assertJsonPath('locale', 'en');
    }

    public function test_json_errors_outside_the_api_prefix_use_the_requested_locale(): void
    {
        $this->getJson('/missing-web-route', ['X-Locale' => 'ar'])
            ->assertNotFound()->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('message', 'العنصر المطلوب غير موجود.');
    }

    public function test_language_negotiation_ignores_languages_with_zero_quality(): void
    {
        $this->getJson('/api/_test/locale', ['Accept-Language' => 'fr;q=1,ar;q=0'])
            ->assertOk()->assertHeader('Content-Language', 'en')->assertJsonPath('locale', 'en');
    }

    public function test_a_real_bearer_token_applies_the_saved_language_to_errors(): void
    {
        $user = User::factory()->client()->create(['locale' => 'ar']);
        $token = $user->createToken('localization-test')->plainTextToken;

        $this->getJson('/api/v1/no-such-route', ['Authorization' => 'Bearer '.$token, 'Accept-Language' => 'en'])
            ->assertNotFound()->assertHeader('Content-Language', 'ar')->assertJsonPath('message', 'العنصر المطلوب غير موجود.');
    }
}
