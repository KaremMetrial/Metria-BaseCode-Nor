<?php

namespace Database\Factories;

use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Referral> */
class ReferralFactory extends Factory
{
    public function definition(): array
    {
        return ['referral_code_id' => ReferralCode::factory(), 'referrer_id' => fn (array $attributes): int => ReferralCode::query()->findOrFail($attributes['referral_code_id'])->user_id, 'referred_user_id' => User::factory()->client()];
    }
}
