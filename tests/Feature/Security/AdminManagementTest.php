<?php

namespace Tests\Feature\Security;

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_approval_requires_permission_and_is_audited(): void
    {
        $this->seed(RbacSeeder::class);
        $vendor = User::factory()->vendor()->pending()->create();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $this->patchJson('/api/v1/admin/users/'.$vendor->id.'/status', ['status' => 'active'])->assertForbidden();
        $admin->givePermissionTo('vendors.approve');
        $this->patchJson('/api/v1/admin/users/'.$vendor->id.'/status', ['status' => 'active'])->assertOk();
        $this->assertSame(UserStatus::ACTIVE, $vendor->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vendor.approved', 'subject_id' => $vendor->id]);
    }

    public function test_blocking_revokes_tokens_and_rejects_admin_targets(): void
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create();
        $user->createToken('test');
        $admin = User::factory()->admin()->create();
        $admin->assignRole('support-agent');
        Sanctum::actingAs($admin);
        $this->patchJson('/api/v1/admin/users/'.$user->id.'/status', ['status' => 'blocked'])->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->patchJson('/api/v1/admin/users/'.$admin->id.'/status', ['status' => 'blocked'])->assertForbidden();
    }

    public function test_block_permission_cannot_bypass_vendor_approval_through_an_intermediate_status(): void
    {
        $this->seed(RbacSeeder::class);
        $vendor = User::factory()->vendor()->create(['status' => UserStatus::INACTIVE]);
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo('users.block');
        Sanctum::actingAs($admin);

        $this->patchJson('/api/v1/admin/users/'.$vendor->id.'/status', ['status' => 'active'])->assertForbidden();
        $this->assertSame(UserStatus::INACTIVE, $vendor->fresh()->status);

        $admin->givePermissionTo('vendors.approve');
        $this->patchJson('/api/v1/admin/users/'.$vendor->id.'/status', ['status' => 'active'])->assertOk();
    }

    public function test_only_super_admin_can_assign_roles_and_never_to_clients(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/admin/users/'.$target->id.'/roles', ['role' => 'finance-admin'])->assertForbidden();
        $admin->assignRole('super-admin');
        $this->postJson('/api/v1/admin/users/'.$target->id.'/roles', ['role' => 'finance-admin'])->assertOk();
        $this->postJson('/api/v1/admin/users/'.$client->id.'/roles', ['role' => 'super-admin'])->assertForbidden();
        $this->assertTrue($target->fresh()->hasRole('finance-admin'));
        $this->assertFalse($client->fresh()->hasRole('super-admin'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.assigned', 'subject_id' => $target->id]);
    }
}
