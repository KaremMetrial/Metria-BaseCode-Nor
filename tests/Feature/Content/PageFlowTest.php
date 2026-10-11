<?php

namespace Tests\Feature\Content;

use App\Models\Country;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PageFlowTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions = ['pages.read', 'pages.manage', 'pages.publish']): User
    {
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->admin()->create();
        $staff->givePermissionTo($permissions);
        Sanctum::actingAs($staff);

        return $staff;
    }

    private function translations(string $content = 'Published content'): array
    {
        return ['en' => ['title' => 'Terms', 'content' => $content], 'ar' => ['title' => 'الشروط', 'content' => 'الشروط العربية']];
    }

    private function publishTerms(): array
    {
        $page = Page::query()->where('slug', 'terms')->firstOrFail();
        $draft = $this->patchJson('/api/v1/admin/pages/'.$page->id, ['draft_version' => $page->draft_version, 'translations' => $this->translations()])->assertOk()->json('data');

        return $this->postJson('/api/v1/admin/pages/'.$page->id.'/publish', ['draft_version' => $draft['draft_version'], 'requires_acceptance' => true])->assertOk()->json('data');
    }

    public function test_drafts_are_private_and_publication_freezes_content_and_navigation(): void
    {
        $this->staff();
        $id = $this->postJson('/api/v1/admin/pages', ['slug' => 'about-us', 'translations' => $this->translations(), 'sort_order' => 2])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/pages/about-us')->assertNotFound();
        $this->postJson('/api/v1/admin/pages/'.$id.'/publish', ['draft_version' => 1, 'requires_acceptance' => false])->assertOk();
        $this->patchJson('/api/v1/admin/pages/'.$id, ['draft_version' => 2, 'translations' => $this->translations('Unpublished secret'), 'sort_order' => 99])->assertOk();
        $this->getJson('/api/v1/pages/about-us')->assertOk()->assertJsonPath('data.content', 'Published content')->assertJsonPath('data.navigation.sort_order', 2);
        $this->getJson('/api/v1/pages/about-us', ['X-Locale' => 'ar'])->assertOk()->assertJsonPath('data.title', 'الشروط');
        $this->getJson('/api/v1/admin/pages/'.$id.'/revisions')->assertOk()->assertJsonPath('data.items.0.translations.en.content', 'Published content');
        $this->postJson('/api/v1/admin/pages/'.$id.'/archive', ['draft_version' => 3])->assertOk();
        $this->getJson('/api/v1/pages/about-us')->assertNotFound();
        $this->assertDatabaseCount('page_revisions', 1);
    }

    public function test_publication_requires_both_languages_and_rejects_stale_edits(): void
    {
        $this->staff();
        $id = $this->postJson('/api/v1/admin/pages', ['slug' => 'policy', 'translations' => ['en' => ['title' => 'Policy', 'content' => 'English']]])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/pages/'.$id.'/publish', ['draft_version' => 1, 'requires_acceptance' => false])->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->patchJson('/api/v1/admin/pages/'.$id, ['draft_version' => 1, 'sort_order' => 4])->assertOk();
        $this->patchJson('/api/v1/admin/pages/'.$id, ['draft_version' => 1, 'sort_order' => 99])->assertConflict();
        $this->postJson('/api/v1/admin/pages/'.$id.'/publish', ['draft_version' => 1, 'requires_acceptance' => false])->assertConflict();
        $this->assertDatabaseHas('pages', ['id' => $id, 'sort_order' => 4, 'status' => 'draft']);
    }

    public function test_content_permissions_and_actor_are_enforced(): void
    {
        $page = Page::factory()->create();
        $this->getJson('/api/v1/admin/pages')->assertUnauthorized();
        $this->staff(['pages.read']);
        $this->getJson('/api/v1/admin/pages')->assertOk();
        $this->postJson('/api/v1/admin/pages/'.$page->id.'/publish', ['draft_version' => 1, 'requires_acceptance' => false])->assertForbidden();
        $this->patchJson('/api/v1/admin/pages/'.$page->id, ['draft_version' => 1, 'sort_order' => 1])->assertForbidden();
        Sanctum::actingAs(User::factory()->client()->create());
        $this->getJson('/api/v1/admin/pages')->assertForbidden();
    }

    public function test_markdown_is_sanitized_and_web_title_is_escaped(): void
    {
        $this->withoutVite();
        $this->staff();
        $translations = $this->translations('<script>alert(1)</script> [link](javascript:alert(1))');
        $translations['en']['title'] = '<script>bad title</script>';
        $id = $this->postJson('/api/v1/admin/pages', ['slug' => 'safe-page', 'translations' => $translations])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/pages/'.$id.'/publish', ['draft_version' => 1, 'requires_acceptance' => false])->assertOk();
        $html = $this->getJson('/api/v1/pages/safe-page')->assertOk()->json('data.content_html');
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->get('/pages/safe-page')->assertOk()->assertDontSee('<script>bad title</script>', false)->assertSee('&lt;script&gt;bad title&lt;/script&gt;', false);
        $this->get('/pages/safe-page?locale=ar')->assertOk()->assertSee('dir="rtl"', false);
    }

    public function test_published_terms_drive_registration_and_keep_acceptance_history(): void
    {
        $staff = $this->staff();
        $first = $this->publishTerms();
        $this->getJson('/api/v1/client/auth/requirements')->assertOk()->assertJsonPath('data.terms_version', $first['terms_version']);
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);
        $data = ['name' => 'Client', 'terms_accepted' => true, 'terms_version' => $first['terms_version'], 'terms_revision_id' => $first['published_revision_id']];
        $this->postJson('/api/v1/client/auth/complete-profile', $data)->assertOk()->assertJsonPath('data.user.profile_completed', true);
        $this->postJson('/api/v1/client/auth/complete-profile', $data)->assertOk();
        $this->assertDatabaseCount('terms_acceptances', 1);
        $this->assertDatabaseHas('terms_acceptances', ['user_id' => $user->id, 'page_revision_id' => $first['published_revision_id']]);
        Sanctum::actingAs($staff);
        $second = $this->publishTerms();
        $this->postJson('/api/v1/admin/pages/'.$first['id'].'/archive', ['draft_version' => $second['draft_version']])->assertConflict()->assertJsonPath('code', 'RESOURCE_IN_USE');
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/v1/client/profile')->assertOk()->assertJsonPath('data.profile_completed', false);
        $this->postJson('/api/v1/client/auth/complete-profile', $data)->assertUnprocessable();
        $this->postJson('/api/v1/client/auth/complete-profile', array_replace($data, ['terms_version' => $second['terms_version'], 'terms_revision_id' => $second['published_revision_id']]))->assertOk();
        $this->assertDatabaseCount('terms_acceptances', 2);
    }

    public function test_editorial_terms_publication_keeps_consent_version(): void
    {
        $this->staff();
        $first = $this->publishTerms();
        $this->postJson('/api/v1/admin/pages/'.$first['id'].'/publish', ['draft_version' => $first['draft_version'], 'requires_acceptance' => false])->assertOk()->assertJsonPath('data.terms_version', $first['terms_version']);
        $this->assertDatabaseCount('page_revisions', 2);
    }

    public function test_seeding_preserves_content_and_does_not_publish_placeholders(): void
    {
        $this->seed(PageSeeder::class);
        $page = Page::query()->where('slug', 'terms')->firstOrFail();
        $page->translate('en')->content = 'Do not replace';
        $page->save();
        $this->seed(PageSeeder::class);
        $this->assertDatabaseCount('pages', 5);
        $this->assertSame('Do not replace', $page->fresh()->translate('en')->content);
        $this->getJson('/api/v1/pages')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_consent_records_the_displayed_revision_after_an_editorial_release(): void
    {
        $this->staff();
        $first = $this->publishTerms();
        $this->postJson('/api/v1/admin/pages/'.$first['id'].'/publish', ['draft_version' => $first['draft_version'], 'requires_acceptance' => false])->assertOk();
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client);
        $data = ['name' => 'Client', 'terms_accepted' => true, 'terms_version' => $first['terms_version']];
        $this->postJson('/api/v1/client/auth/complete-profile', $data)->assertUnprocessable();
        $this->postJson('/api/v1/client/auth/complete-profile', $data + ['terms_revision_id' => 99999])->assertUnprocessable();
        $this->postJson('/api/v1/client/auth/complete-profile', $data + ['terms_revision_id' => $first['published_revision_id']])->assertOk();
        $this->assertDatabaseHas('terms_acceptances', ['user_id' => $client->id, 'page_revision_id' => $first['published_revision_id']]);
    }

    public function test_contact_web_form_renders_and_submits_without_email(): void
    {
        $this->withoutVite();
        $this->staff();
        $country = Country::factory()->withIso2('EG')->create();
        $id = $this->postJson('/api/v1/admin/pages', ['slug' => 'contact-us', 'translations' => $this->translations()])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/pages/'.$id.'/publish', ['draft_version' => 1, 'requires_acceptance' => false])->assertOk();
        $this->get('/pages/contact-us?locale=ar')->assertOk()->assertSee('أرسل رسالة')->assertSee('name="phone"', false)->assertDontSee('name="email"', false);
        $data = ['locale' => 'ar', 'request_id' => (string) Str::uuid(), 'name' => 'Client', 'phone' => '01012345678', 'country_id' => $country->id, 'subject' => 'Support', 'message' => 'Please call me.'];
        $this->post('/pages/contact-us/message', $data)->assertRedirect(route('pages.show', ['slug' => 'contact-us', 'locale' => 'ar']))->assertSessionHas('contact_reference');
        $this->assertDatabaseHas('contact_messages', ['phone' => '+201012345678', 'user_id' => null]);
        $this->from('/pages/contact-us?locale=ar')->post('/pages/contact-us/message', array_replace($data, ['phone' => '123']))->assertRedirect()->assertSessionHasErrors('phone');
        $this->from('/pages/contact-us?locale=ar')->post('/pages/contact-us/message', array_replace($data, ['subject' => '']))->assertRedirect()->assertSessionHasErrors('subject');
        $this->assertSame('ar', app()->getLocale());
        $this->assertDatabaseCount('contact_messages', 1);
    }
}
