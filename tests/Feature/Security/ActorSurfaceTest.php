<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActorSurfaceTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('actors')]
    public function test_matching_actor_can_read_its_profile(string $actor): void
    {
        $user = User::factory()->{$actor}()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/'.$actor.'/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.type', $actor);
    }

    #[DataProvider('actors')]
    public function test_profile_returns_401_without_authentication(string $actor): void
    {
        $this->getJson('/api/v1/'.$actor.'/profile')->assertUnauthorized();
    }

    #[DataProvider('differentActors')]
    public function test_other_actor_surface_returns_403_without_reading_or_updating_profile(string $actor, string $surface): void
    {
        $user = User::factory()->{$actor}()->create(['name' => 'Original name']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/'.$surface.'/profile')->assertForbidden();
        $this->patchJson('/api/v1/'.$surface.'/profile', ['name' => 'Changed name'])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Original name']);
    }

    public function test_client_with_finance_permission_still_receives_403_on_admin_surface(): void
    {
        $this->seed(RbacSeeder::class);
        $client = User::factory()->client()->create();
        $client->givePermissionTo('wallets.read');
        $this->assertTrue($client->can('wallets.read'));
        Sanctum::actingAs($client);

        $this->getJson('/api/v1/admin/wallets')->assertForbidden();
    }

    /** @return array<string, array{string}> */
    public static function actors(): array
    {
        return [
            'admin' => ['admin'],
            'client' => ['client'],
            'vendor' => ['vendor'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function differentActors(): array
    {
        return [
            'admin to client' => ['admin', 'client'],
            'admin to vendor' => ['admin', 'vendor'],
            'client to admin' => ['client', 'admin'],
            'client to vendor' => ['client', 'vendor'],
            'vendor to admin' => ['vendor', 'admin'],
            'vendor to client' => ['vendor', 'client'],
        ];
    }
}
