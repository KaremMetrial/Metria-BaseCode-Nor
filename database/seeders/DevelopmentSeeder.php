<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Demo data is explicitly opt-in, including when individual seeders are invoked. */
abstract class DevelopmentSeeder extends Seeder
{
    final public function run(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            throw new RuntimeException('Demo seeders are only available in local, development and testing environments.');
        }

        DB::transaction(fn () => $this->seed());
    }

    abstract protected function seed(): void;

    protected function user(string $key): User
    {
        if ($key === 'admin') {
            $user = User::query()->where('email', config('access.dev_admin.email'))->firstOrFail();
            if ($user->type !== UserType::ADMIN) {
                throw new RuntimeException('The development administrator email belongs to a non-admin account.');
            }

            return $user;
        }
        $identity = DemoUserSeeder::ACCOUNTS[$key];

        return User::query()->where($identity['email'] === null ? 'phone' : 'email', $identity['email'] ?? $identity['phone'])
            ->where('type', $identity['type'])->firstOrFail();
    }
}
