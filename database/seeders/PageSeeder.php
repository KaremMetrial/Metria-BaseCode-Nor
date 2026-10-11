<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['terms' => ['Terms and conditions', 'الشروط والأحكام'], 'privacy-policy' => ['Privacy policy', 'سياسة الخصوصية'], 'about-us' => ['About us', 'من نحن'], 'refund-policy' => ['Refund policy', 'سياسة الاسترداد'], 'contact-us' => ['Contact us', 'تواصل معنا']] as $slug => $titles) {
            $page = Page::query()->firstOrCreate(['slug' => $slug]);
            foreach (['en', 'ar'] as $index => $locale) {
                if (! $page->hasTranslation($locale)) {
                    $page->translateOrNew($locale)->fill(['title' => $titles[$index], 'content' => '']);
                }
            }
            $page->save();
        }
    }
}
