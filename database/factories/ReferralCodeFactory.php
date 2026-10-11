<?php

namespace Database\Factories;

use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReferralCode> */
class ReferralCodeFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory()->client()->state(['profile_completed_at' => now(), 'terms_accepted_at' => now(), 'terms_version' => '1']), 'code' => strtoupper(bin2hex(random_bytes(8)))];
    }
}
