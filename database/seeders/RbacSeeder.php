<?php

namespace Database\Seeders;

use App\Support\Access\PermissionRegistry;
use App\Support\Access\RoleRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds roles and permissions from the registries.
 *
 * Idempotent: re-running syncs existing roles rather than duplicating them, so
 * this is safe in a deploy pipeline.
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $guard = $this->guard();

        // Spatie caches the permission map; without clearing it a freshly
        // seeded permission is invisible until the cache expires.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionRegistry::all() as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        // Invalidate again *after* creating permissions.
        //
        // This spatie release caches the permission map but does not clear it
        // when a permission is created, so the map consulted while creating the
        // permissions above is already stale. Without this second call,
        // syncPermissions() cannot see the permissions it was just handed and
        // throws PermissionDoesNotExist -- an RBAC break that only shows up on a
        // cold cache, i.e. on the first deploy.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleRegistry::roles() as $role => $permissions) {
            Role::findOrCreate($role, $guard)->syncPermissions($permissions);
        }

        Role::findOrCreate(RoleRegistry::SUPER_ADMIN, $guard)->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Permissions are granted per guard. The application uses a single guard
     * (`web`) even for token-authenticated requests, because Sanctum's guard
     * delegates to the same provider and a second guard would create a parallel
     * permission universe.
     */
    private function guard(): string
    {
        return (string) config('auth.defaults.guard', 'web');
    }
}
