<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Media> */
class MediaFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory()->client(), 'purpose' => 'support', 'status' => 'ready', 'disk' => 'local',
            'path' => 'media/'.Str::uuid().'.png', 'original_name' => 'image.png', 'mime_type' => 'image/png', 'size' => 100,
            'inspection' => 'reencoded', 'expires_at' => now()->addDay()];
    }
}
