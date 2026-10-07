<?php

namespace Database\Factories;

use App\Models\MyFatoorahOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MyFatoorahOperation> */
class MyFatoorahOperationFactory extends Factory
{
    public function definition(): array
    {
        return ['uuid' => fake()->uuid(), 'actor_id' => User::factory()->admin(), 'operation' => 'transfers.create', 'status' => 'uncertain', 'idempotency_key' => fake()->uuid(), 'request_hash' => hash('sha256', fake()->uuid())];
    }
}
