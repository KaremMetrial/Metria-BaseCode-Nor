<?php

namespace Database\Factories;

use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShareLink> */
class ShareLinkFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory()->client(), 'code' => bin2hex(random_bytes(16)), 'target_type' => 'home', 'target_value' => ''];
    }
}
