<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\Access\PermissionRegistry;
use App\Support\Access\RoleRegistry;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_every_registered_permission_is_seeded(): void
    {
        $this->assertSame(
            count(PermissionRegistry::all()),
            Permission::query()->where('guard_name', 'web')->count(),
        );
    }

    public function test_finance_admin_can_refund_but_support_agent_cannot(): void
    {
        $finance = $this->adminWithRole(RoleRegistry::FINANCE_ADMIN);
        $support = $this->adminWithRole(RoleRegistry::SUPPORT_AGENT);

        $this->assertTrue($finance->can(PermissionRegistry::PAYMENTS_REFUND));
        $this->assertTrue($finance->can(PermissionRegistry::PAYMENTS_READ));

        $this->assertFalse($support->can(PermissionRegistry::PAYMENTS_REFUND));
        $this->assertTrue($support->can(PermissionRegistry::USERS_BLOCK));
    }

    public function test_an_admin_with_no_role_is_denied_everything(): void
    {
        $user = User::factory()->admin()->withoutPhone()->create();

        $this->assertFalse($user->can(PermissionRegistry::USERS_READ));
        $this->assertFalse($user->can(PermissionRegistry::WALLETS_ADJUST));
    }

    public function test_an_unseeded_permission_name_is_denied(): void
    {
        $this->assertFalse($this->adminWithRole(RoleRegistry::FINANCE_ADMIN)->can('nonexistent.ability'));
    }

    public function test_super_admin_bypasses_every_ability(): void
    {
        $superAdmin = $this->adminWithRole(RoleRegistry::SUPER_ADMIN);

        foreach (PermissionRegistry::all() as $permission) {
            $this->assertTrue($superAdmin->can($permission), "Super admin should bypass {$permission}");
        }

        // ...including abilities that are not permissions at all, because the
        // bypass runs before policy resolution.
        $this->assertTrue($superAdmin->can('some.future.ability'));
    }

    public function test_super_admin_is_granted_no_permissions_explicitly(): void
    {
        // Proves authority comes from the bypass, not from a mass of grants:
        // a newly registered permission is covered automatically and can never
        // be forgotten.
        $role = Role::findByName(RoleRegistry::SUPER_ADMIN);

        $this->assertCount(0, $role->permissions);
    }

    public function test_an_ordinary_admin_does_not_get_the_bypass(): void
    {
        $this->assertFalse(
            $this->adminWithRole(RoleRegistry::SUPPORT_AGENT)->can('some.future.ability'),
        );
    }

    public function test_reseeding_is_idempotent(): void
    {
        $permissions = Permission::count();
        $roles = Role::count();

        $this->seed(RbacSeeder::class);

        $this->assertSame($permissions, Permission::count());
        $this->assertSame($roles, Role::count());
    }

    public function test_every_registry_role_exists_after_seeding(): void
    {
        foreach (RoleRegistry::all() as $role) {
            $this->assertNotNull(Role::findByName($role), "Role {$role} was not seeded");
        }
    }

    private function adminWithRole(string $role): User
    {
        $user = User::factory()->admin()->withoutPhone()->create();
        $user->assignRole($role);

        return $user->fresh();
    }
}
