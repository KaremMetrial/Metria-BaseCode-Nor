<?php

namespace Database\Factories;

use App\Models\MyFatoorahEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MyFatoorahEntity> */
class MyFatoorahEntityFactory extends Factory
{
    public function definition(): array
    {
        return ['kind' => 'supplier', 'reference' => (string) fake()->unique()->numberBetween(100, 999999), 'status' => 'Pending', 'snapshot' => [], 'needs_refresh' => false, 'synced_at' => now()];
    }
}
