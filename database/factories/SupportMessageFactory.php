<?php

namespace Database\Factories;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportMessage> */
class SupportMessageFactory extends Factory
{
    public function definition(): array
    {
        return ['support_conversation_id' => SupportConversation::factory(), 'sender_id' => fn (array $attributes) => SupportConversation::query()->findOrFail($attributes['support_conversation_id'])->user_id, 'sender_type' => 'client', 'client_message_id' => fake()->uuid(), 'body' => 'Please help.', 'created_at' => now()];
    }
}
