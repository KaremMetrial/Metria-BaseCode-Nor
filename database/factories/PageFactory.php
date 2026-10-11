<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Page> */
class PageFactory extends Factory
{
    public function definition(): array
    {
        return ['slug' => fake()->unique()->slug(), 'en' => ['title' => 'Example page', 'content' => '# Example content'], 'ar' => ['title' => 'صفحة تجريبية', 'content' => '# محتوى تجريبي']];
    }
}
