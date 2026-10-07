<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Monotonic counter that yields unique, structurally valid E.164 numbers.
     *
     * Egyptian mobiles are used because they exercise the same libphonenumber
     * code path as any other country while being easy to keep valid:
     * "+20" + "1" + 9 digits.
     */
    protected static int $phoneSequence = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => $this->uniquePhone(),
            'phone_country_id' => null,
            'phone_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'type' => UserType::CLIENT,
            'status' => UserStatus::ACTIVE,
            'locale' => 'en',
            'timezone' => 'UTC',
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'type' => UserType::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    public function vendor(): static
    {
        return $this->state(fn (): array => [
            'type' => UserType::VENDOR,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    public function client(): static
    {
        return $this->state(fn (): array => [
            'type' => UserType::CLIENT,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::PENDING]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::SUSPENDED]);
    }

    public function blocked(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::BLOCKED]);
    }

    /**
     * A phone/OTP actor that has never set a password.
     */
    public function withoutPassword(): static
    {
        return $this->state(fn (): array => ['password' => null]);
    }

    /**
     * A phone-less admin-style account that authenticates by email only.
     */
    public function withoutPhone(): static
    {
        return $this->state(fn (): array => [
            'phone' => null,
            'phone_verified_at' => null,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function withUnverifiedPhone(): static
    {
        return $this->state(fn (): array => ['phone_verified_at' => null]);
    }

    private function uniquePhone(): string
    {
        return '+20'.str_pad((string) (1_000_000_000 + static::$phoneSequence++), 10, '0', STR_PAD_LEFT);
    }
}
