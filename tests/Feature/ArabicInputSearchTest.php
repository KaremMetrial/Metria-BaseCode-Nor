<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\SupportConversation;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ArabicInputSearchTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['json'])]
    #[TestWith(['form'])]
    public function test_digits_are_normalized_recursively_before_validation_without_changing_secrets(string $format): void
    {
        Route::post('/api/_test/digits', function (Request $request) {
            $request->validate(['phone' => ['regex:/^01012345678$/'], 'code' => ['digits:6'], 'page' => ['integer'], 'nested.*.amount' => ['integer']]);

            return response()->json($request->all());
        });
        $payload = ['phone' => '٠١٠١٢٣٤٥٦٧٨', 'code' => '۰۱۲۳۴۵', 'nested' => [['amount' => '١٢٣', 'title' => 'عرض ۱۲']], 'password' => 'Pass١٢', 'password_confirmation' => 'Pass١٢', 'token' => 'Push١٢', 'cursor' => 'Opaque۱۲', 'redirect_url' => 'https://example.test/١٢', 'enabled' => true, 'count' => 5, 'empty' => null];
        $response = $format === 'json' ? $this->postJson('/api/_test/digits?page=٢', $payload) : $this->post('/api/_test/digits?page=٢', $payload, ['Accept' => 'application/json']);
        $response->assertOk()->assertJsonPath('phone', '01012345678')->assertJsonPath('code', '012345')->assertJsonPath('page', '2')->assertJsonPath('nested.0.amount', '123')->assertJsonPath('nested.0.title', 'عرض 12')
            ->assertJsonPath('password', 'Pass١٢')->assertJsonPath('password_confirmation', 'Pass١٢')->assertJsonPath('token', 'Push١٢')->assertJsonPath('cursor', 'Opaque۱۲')->assertJsonPath('redirect_url', 'https://example.test/١٢')
            ->assertJsonPath('enabled', true)->assertJsonPath('count', 5)->assertJsonPath('empty', null);
    }

    public function test_normalized_numbers_are_saved_from_nested_admin_input(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);
        $id = $this->postJson('/api/v1/admin/categories', ['sort_order' => '١٢', 'translations' => ['en' => ['name' => 'Room ۱۲'], 'ar' => ['name' => 'غرفة ١٢']]])
            ->assertCreated()->json('data.id');
        $this->assertDatabaseHas('categories', ['id' => $id, 'sort_order' => 12]);
        $this->assertDatabaseHas('category_translations', ['category_id' => $id, 'locale' => 'ar', 'name' => 'غرفة 12']);
        $this->getJson('/api/v1/categories?'.http_build_query(['search' => 'غرفة ١٢']))->assertOk()->assertJsonPath('data.items.0.id', $id);
    }

    public function test_signed_webhook_body_and_decoded_values_remain_unchanged(): void
    {
        Route::post('/api/v1/webhooks/_test', fn (Request $request) => response()->json(['raw' => $request->getContent(), 'value' => $request->input('value'), 'signature' => $request->header('Signature')]));
        $payload = '{"value":"١٢٣"}';
        $this->call('POST', '/api/v1/webhooks/_test', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_SIGNATURE' => 'sig١٢'], $payload)
            ->assertOk()->assertJsonPath('raw', $payload)->assertJsonPath('value', '١٢٣')->assertJsonPath('signature', 'sig١٢');
    }

    #[TestWith(['ابراهيم', 'إِبْرَاهِيم'])]
    #[TestWith(['قهوه ابراهيم اللبناني', 'قَهْوَةُ إِبْرَاهِيم اللبنانى'])]
    #[TestWith(['فول و تميس', 'فول وتميس'])]
    #[TestWith(['اللبناني', 'اللبنانى'])]
    #[TestWith(['كباب', 'کباب'])]
    public function test_arabic_fallback_searches_arabic_translations_even_with_english_display(string $search, string $name): void
    {
        $category = $this->category($name);
        $this->getJson('/api/v1/categories?'.http_build_query(['locale' => 'en', 'search' => $search, 'per_page' => '١']))
            ->assertOk()->assertJsonPath('data.items.0.id', $category->id)->assertJsonPath('data.items.0.name', 'English display')->assertJsonPath('data.meta.total', 1)->assertJsonPath('data.meta.per_page', 1);
        $this->assertSame($name, $category->fresh()->translate('ar')->name);
    }

    public function test_literal_results_take_priority_and_fallback_obeys_other_filters_and_visibility(): void
    {
        $root = $this->category('جذر');
        $literal = $this->category('فول و تميس');
        $compact = $this->category('فول وتميس', ['parent_id' => $root->id]);
        $this->getJson('/api/v1/categories?'.http_build_query(['search' => 'فول و تميس']))->assertOk()->assertJsonPath('data.items.0.id', $literal->id)->assertJsonPath('data.meta.total', 1);
        $this->getJson('/api/v1/categories?'.http_build_query(['search' => 'فول و تميس', 'parent_id' => $root->id]))->assertOk()->assertJsonPath('data.items.0.id', $compact->id)->assertJsonPath('data.meta.total', 1);
        $literal->update(['is_active' => false]);
        $this->getJson('/api/v1/categories?'.http_build_query(['search' => 'فول و تميس']))->assertOk()->assertJsonPath('data.items.0.id', $compact->id)->assertJsonPath('data.meta.total', 1);
    }

    public function test_heh_fallback_is_used_only_when_ordinary_arabic_normalization_has_no_results(): void
    {
        $ordinary = $this->category('قَهْوَة');
        $this->category('قهوه');
        $this->getJson('/api/v1/categories?'.http_build_query(['search' => 'قهوة']))->assertOk()->assertJsonPath('data.items.0.id', $ordinary->id)->assertJsonPath('data.meta.total', 1);
    }

    public function test_english_spacing_and_non_arabic_translation_rows_are_not_arabic_normalized(): void
    {
        Category::create(['is_active' => true, 'en' => ['name' => 'Ice Cream'], 'ar' => ['name' => 'Ice Cream']]);
        Category::create(['is_active' => true, 'en' => ['name' => 'فول وتميس'], 'ar' => ['name' => 'اسم آخر']]);
        $this->getJson('/api/v1/categories?search=Icecream')->assertOk()->assertJsonPath('data.meta.total', 0);
        $this->getJson('/api/v1/categories?search=Ice%20Cream')->assertOk()->assertJsonPath('data.meta.total', 1);
        $this->getJson('/api/v1/categories?'.http_build_query(['search' => 'فول و تميس']))->assertOk()->assertJsonPath('data.meta.total', 0);
    }

    #[TestWith(['%'])]
    #[TestWith(['_'])]
    #[TestWith(["' OR 1=1 --"])]
    #[TestWith(['َـ'])]
    public function test_search_treats_wildcards_literally_and_marks_do_not_match_everything(string $search): void
    {
        $this->category('قهوة');
        $this->getJson('/api/v1/categories?'.http_build_query(['search' => $search]))->assertOk()->assertJsonPath('data.meta.total', 0);
    }

    public function test_normalized_results_paginate_without_duplicates(): void
    {
        $a = $this->category('فول وتميس 1');
        $b = $this->category('فول وتميس 2');
        $query = http_build_query(['search' => 'فول و تميس', 'sort' => 'id', 'per_page' => '١']);
        $this->getJson('/api/v1/categories?'.$query)->assertOk()->assertJsonPath('data.items.0.id', $a->id)->assertJsonPath('data.meta.total', 2);
        $this->getJson('/api/v1/categories?'.$query.'&page=٢')->assertOk()->assertJsonPath('data.items.0.id', $b->id)->assertJsonPath('data.meta.total', 2);
    }

    public function test_conversation_fallback_cannot_be_suppressed_by_another_accounts_literal_match(): void
    {
        $user = User::factory()->client()->create();
        $own = SupportConversation::factory()->create(['user_id' => $user->id, 'subject' => 'فول وتميس']);
        SupportConversation::factory()->create(['subject' => 'فول و تميس']);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/client/support/conversations?'.http_build_query(['search' => 'فول و تميس']))->assertOk()->assertJsonPath('data.items.0.id', $own->id)->assertJsonPath('data.meta.total', 1);
    }

    public function test_published_page_search_uses_snapshot_language_and_navigation_scope(): void
    {
        $page = Page::factory()->create(['en' => ['title' => 'Draft secret', 'content' => 'Draft']]);
        $revision = PageRevision::factory()->create(['page_id' => $page->id, 'translations' => ['en' => ['title' => 'Published', 'content' => 'Text'], 'ar' => ['title' => 'قَهْوَةُ إِبْرَاهِيم', 'content' => 'نص']]]);
        $page->forceFill(['status' => 'published', 'published_revision_id' => $revision->id])->save();
        $this->getJson('/api/v1/pages?'.http_build_query(['search' => 'قهوه ابراهيم', 'locale' => 'en', 'show_in_footer' => '١']))->assertOk()->assertJsonPath('data.0.title', 'Published')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/pages?search=Draft')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/pages?'.http_build_query(['search' => 'قهوه ابراهيم', 'show_in_footer' => '٠']))->assertOk()->assertJsonCount(0, 'data');
    }

    /** @param array<string, mixed> $attributes */
    private function category(string $name, array $attributes = []): Category
    {
        return Category::create($attributes + ['is_active' => true, 'en' => ['name' => 'English display'], 'ar' => ['name' => $name]]);
    }
}
