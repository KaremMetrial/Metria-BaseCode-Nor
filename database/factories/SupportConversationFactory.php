<?php

namespace Database\Factories;

use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportConversation> */
class SupportConversationFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory()->client(), 'request_id' => fake()->uuid(), 'request_hash' => hash('sha256', fake()->uuid()), 'subject' => 'Help with my account'];
    }
}
