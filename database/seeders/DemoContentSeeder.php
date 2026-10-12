<?php

namespace Database\Seeders;

use App\Actions\Admin\Category\SaveCategory;
use App\Actions\Admin\Content\PublishPage;
use App\Models\Category;
use App\Models\ContactSetting;
use App\Models\Page;

final class DemoContentSeeder extends DevelopmentSeeder
{
    protected function seed(): void
    {
        $root = $this->category('Demo food', 'أطعمة تجريبية', null, true, 10);
        $this->category('Demo coffee', 'قَهْوَة إِبْرَاهِيم', $root->id, true, 20);
        $this->category('Demo breakfast', 'فول وتميس', $root->id, true, 30);
        $this->category('Demo hidden category', 'تصنيف تجريبي مخفي', null, false, 40);

        foreach (['terms', 'privacy-policy', 'about-us', 'refund-policy', 'contact-us'] as $slug) {
            $page = Page::query()->where('slug', $slug)->with('translations')->firstOrFail();
            if ($page->published_revision_id !== null || $page->translations->contains(fn ($row): bool => trim($row->content) !== '')) {
                continue;
            }
            foreach (['en' => 'Demo content for local testing only. Replace before publishing your application.', 'ar' => 'محتوى تجريبي للاختبار المحلي فقط. يجب استبداله بمحتوى التطبيق الفعلي.'] as $locale => $body) {
                $page->translateOrNew($locale)->fill(['content' => $body]);
            }
            $page->save();
            app(PublishPage::class)->execute($page, $page->draft_version, $slug === 'terms', $this->user('admin'));
        }
        foreach (['demo-help' => 'published', 'demo-draft' => 'draft'] as $slug => $status) {
            if (Page::query()->where('slug', $slug)->exists()) {
                continue;
            }
            $page = new Page;
            $page->fill(['slug' => $slug, 'sort_order' => 50, 'translations' => [
                'en' => ['title' => 'Demo help', 'content' => 'Demo help: sign in, complete your profile, then explore your wallet and support chat.'],
                'ar' => ['title' => 'المساعدة التجريبية', 'content' => 'سجّل الدخول وأكمل ملفك ثم جرّب المحفظة ومحادثة الدعم.'],
            ]])->save();
            if ($status === 'published') {
                app(PublishPage::class)->execute($page->refresh(), $page->draft_version, false, $this->user('admin'));
            }
        }
        $setting = ContactSetting::query()->findOrFail(1);
        if ($setting->settings === []) {
            $setting->forceFill(['settings' => ['phone' => null, 'whatsapp' => null, 'social_links' => [], 'translations' => [
                'en' => ['address' => 'Demo office, Cairo', 'working_hours' => 'Sunday–Thursday, 09:00–17:00', 'intro' => 'Demo contact information.'],
                'ar' => ['address' => 'مكتب تجريبي، القاهرة', 'working_hours' => 'الأحد–الخميس، 09:00–17:00', 'intro' => 'بيانات تواصل تجريبية.'],
            ]]])->save();
        }
    }

    private function category(string $english, string $arabic, ?int $parent, bool $active, int $sort): Category
    {
        return Category::query()->whereHas('translations', fn ($query) => $query->where('locale', 'en')->where('name', $english))->first()
            ?? app(SaveCategory::class)->execute(null, ['parent_id' => $parent, 'is_active' => $active, 'sort_order' => $sort,
                'translations' => ['en' => ['name' => $english, 'description' => 'Demo category'], 'ar' => ['name' => $arabic, 'description' => 'تصنيف تجريبي']]]);
    }
}
