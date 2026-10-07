<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Access\RoleRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the application's baseline data.
 *
 * NOTE: this class deliberately does NOT use Laravel's `WithoutModelEvents`
 * trait. Any seeder it calls would then run with model events muted, and
 * Astrotomic persists translations *through* the `saved` event -- so every
 * translatable seeder here would write zero translation rows while reporting
 * success. That failure is invisible until a client sees a blank country name.
 *
 * A future seeder that must suppress events can opt in locally with
 * `$this->withoutModelEvents(...)` instead of muting them for all of them.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles and permissions are reference data: always seeded, always
        // idempotent.
        $this->call(RbacSeeder::class);

        // Location reference data, in dependency order: a city needs its
        // governorate, which needs its country. All three are idempotent, so this
        // is safe to re-run on every deploy.
        $this->call([
            CountrySeeder::class,
            GovernorateSeeder::class,
            CitySeeder::class,
        ]);

        $this->seedDevelopmentAdmin();
    }

    /**
     * Convenience administrator for local development.
     *
     * Guarded against production on purpose: a documented default
     * email/password pair shipped to production is a backdoor, and "we'll
     * remove it later" is not a control.
     */
    private function seedDevelopmentAdmin(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            return;
        }

        $email = (string) config('access.dev_admin.email');

        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        $admin = User::factory()
            ->admin()
            ->withoutPhone()
            ->create([
                'name' => 'Metrial Admin',
                'email' => $email,
                'password' => Hash::make((string) config('access.dev_admin.password')),
            ]);

        $admin->assignRole(RoleRegistry::SUPER_ADMIN);
    }
}
