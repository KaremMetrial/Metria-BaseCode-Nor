<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActorGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'actor:admin'])
            ->get('/api/_test/admin', fn () => response()->json(['ok' => true]));

        Route::middleware(['api', 'auth:sanctum', 'actor:client'])
            ->get('/api/_test/client', fn () => response()->json(['ok' => true]));

        Route::middleware(['api', 'auth:sanctum', 'actor:admin,vendor'])
            ->get('/api/_test/admin-or-vendor', fn () => response()->json(['ok' => true]));
    }

    public function test_the_actor_and_active_aliases_are_registered(): void
    {
        $aliases = app('router')->getMiddleware();

        $this->assertSame(EnsureUserType::class, $aliases['actor']);
        $this->assertSame(EnsureAccountIsActive::class, $aliases['active']);
    }

    public function test_matching_actor_is_allowed(): void
    {
        Sanctum::actingAs(User::factory()->admin()->withoutPhone()->create());

        $this->getJson('/api/_test/admin')->assertOk()->assertJson(['ok' => true]);
    }

    public function test_client_token_cannot_reach_the_admin_surface(): void
    {
        // This is the reason `actor` exists: a client's token is a perfectly
        // valid Sanctum token, so auth:sanctum alone would allow this call.
        Sanctum::actingAs(User::factory()->client()->create());

        $this->getJson('/api/_test/admin')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_vendor_token_cannot_reach_the_admin_surface(): void
    {
        Sanctum::actingAs(User::factory()->vendor()->create());

        $this->getJson('/api/_test/admin')->assertStatus(403);
    }

    public function test_admin_token_cannot_reach_the_client_surface(): void
    {
        Sanctum::actingAs(User::factory()->admin()->withoutPhone()->create());

        $this->getJson('/api/_test/client')->assertStatus(403);
    }

    public function test_multiple_allowed_types_are_honoured(): void
    {
        Sanctum::actingAs(User::factory()->vendor()->create());

        $this->getJson('/api/_test/admin-or-vendor')->assertOk();

        Sanctum::actingAs(User::factory()->admin()->withoutPhone()->create());

        $this->getJson('/api/_test/admin-or-vendor')->assertOk();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/_test/admin')
            ->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_denied_response_does_not_leak_the_required_actor(): void
    {
        Sanctum::actingAs(User::factory()->client()->create());

        $response = $this->getJson('/api/_test/admin');

        // The reason is logged for operators, never returned to the caller.
        $this->assertStringNotContainsString('admin', (string) $response->getContent());
    }
}
