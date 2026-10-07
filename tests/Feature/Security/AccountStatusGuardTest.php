<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountStatusGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'actor:vendor', 'active'])
            ->get('/api/_test/active', fn () => response()->json(['ok' => true]));
    }

    public function test_active_account_is_allowed(): void
    {
        Sanctum::actingAs(User::factory()->vendor()->create());

        $this->getJson('/api/_test/active')->assertOk()->assertJson(['ok' => true]);
    }

    public function test_pending_account_is_told_it_is_under_review(): void
    {
        // Distinct from "disabled": the user needs a different screen.
        Sanctum::actingAs(User::factory()->vendor()->pending()->create());

        $this->getJson('/api/_test/active')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'ACCOUNT_PENDING',
            ]);
    }

    public function test_blocked_account_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->vendor()->blocked()->create());

        $this->getJson('/api/_test/active')
            ->assertStatus(403)
            ->assertJson(['code' => 'ACCOUNT_DISABLED']);
    }

    public function test_suspended_account_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->vendor()->suspended()->create());

        $this->getJson('/api/_test/active')->assertStatus(403);
    }
}
