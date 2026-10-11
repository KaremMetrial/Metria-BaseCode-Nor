<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PageRevision> */
class PageRevisionFactory extends Factory
{
    public function definition(): array
    {
        return ['page_id' => Page::factory(), 'version' => 1, 'translations' => ['en' => ['title' => 'Terms', 'content' => 'Example terms'], 'ar' => ['title' => 'الشروط', 'content' => 'شروط تجريبية']], 'navigation' => ['sort_order' => 0, 'show_in_footer' => true, 'show_in_settings' => true], 'published_by' => User::factory()->admin(), 'published_at' => now()];
    }
}
