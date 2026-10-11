<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactMessage> */
class ContactMessageFactory extends Factory
{
    public function definition(): array
    {
        return ['reference' => fake()->uuid(), 'request_id' => fake()->uuid(), 'request_hash' => hash('sha256', fake()->uuid()), 'name' => fake()->name(), 'phone' => '+201012345678', 'subject' => 'Contact inquiry', 'message' => 'Please contact me.'];
    }
}
