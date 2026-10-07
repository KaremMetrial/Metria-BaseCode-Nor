<?php

namespace Tests\Feature\Localization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
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
}
