<?php

namespace Tests\Feature;

use App\Models\ContactSetting;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AppConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_receive_only_public_startup_settings(): void
    {
        config(['languages.default' => 'en', 'languages.fallback' => 'en', 'otp.ttl_seconds' => 180, 'otp.cooldown_seconds' => 45,
            'realtime.secret' => 'private-signing-secret', 'media.uploads.avatar.internal' => 'private-upload-setting']);
        ContactSetting::query()->findOrFail(1)->forceFill(['settings' => ['internal' => 'private-contact-setting',
            'social_links' => [['label' => 'Website', 'url' => 'https://example.com', 'internal' => 'private-link-setting']]]])->save();

        $response = $this->getJson('/api/v1/config', ['X-Locale' => 'ar'])->assertOk()
            ->assertHeader('Content-Language', 'ar')->assertHeader('Cache-Control', 'no-cache, private')->assertHeader('ETag')
            ->assertJsonPath('data.localization', ['default' => 'en', 'fallback' => 'en', 'current' => 'ar', 'header' => 'X-Locale', 'supported' => [
                ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'rtl' => false],
                ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'rtl' => true],
            ]])
            ->assertJsonPath('data.client_auth.otp', ['expires_in_seconds' => 180, 'resend_after_seconds' => 45])
            ->assertJsonPath('data.uploads.avatar', ['max_size_bytes' => 2097152, 'extensions' => ['jpg', 'jpeg', 'png', 'webp'], 'image_max_width' => 2048, 'image_max_height' => 2048])
            ->assertJsonPath('data.uploads.support.max_size_bytes', 10485760)
            ->assertJsonPath('data.uploads.page.max_size_bytes', 5242880)
            ->assertJsonPath('data.contact', ['phone' => null, 'whatsapp' => null, 'social_links' => [['label' => 'Website', 'url' => 'https://example.com']], 'address' => null, 'working_hours' => null, 'intro' => null]);

        $this->assertSame(['localization', 'client_auth', 'contact', 'uploads'], array_keys($response->json('data')));
        $this->assertStringNotContainsString('private-', $response->getContent());
        $requirements = $this->getJson('/api/v1/client/auth/requirements', ['X-Locale' => 'ar'])->assertOk()->json('data');
        $this->assertSame($requirements, $response->json('data.client_auth.requirements'));
    }

    public function test_conditional_requests_are_separated_by_language(): void
    {
        $english = $this->getJson('/api/v1/config', ['X-Locale' => 'en'])->assertOk();
        $etag = $english->headers->get('ETag');

        $this->getJson('/api/v1/config', ['X-Locale' => 'en', 'If-None-Match' => $etag])->assertStatus(304)->assertContent('');
        $this->getJson('/api/v1/config', ['X-Locale' => 'ar', 'If-None-Match' => $etag])->assertOk()->assertJsonPath('data.localization.current', 'ar');
        $this->getJson('/api/v1/config?locale=en', ['X-Locale' => 'ar'])->assertOk()->assertJsonPath('data.localization.current', 'en');
        $this->getJson('/api/v1/config', ['X-Locale' => 'xx'])->assertUnprocessable()->assertJsonPath('code', 'UNSUPPORTED_LOCALE');
    }

    public function test_saved_user_locale_does_not_leak_into_other_users_responses(): void
    {
        Sanctum::actingAs(User::factory()->client()->create(['locale' => 'ar']));
        $arabic = $this->getJson('/api/v1/config')->assertOk()->assertJsonPath('data.localization.current', 'ar');
        Sanctum::actingAs(User::factory()->client()->create(['locale' => 'en']));

        $this->getJson('/api/v1/config', ['If-None-Match' => $arabic->headers->get('ETag')])
            ->assertOk()->assertJsonPath('data.localization.current', 'en')->assertHeader('Cache-Control', 'no-cache, private');
    }

    public function test_admin_contact_changes_are_visible_immediately_and_change_the_etag(): void
    {
        $etag = $this->getJson('/api/v1/config', ['X-Locale' => 'ar'])->assertOk()->headers->get('ETag');
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->admin()->create();
        $staff->givePermissionTo('contact.settings');
        Sanctum::actingAs($staff);
        $this->patchJson('/api/v1/admin/contact-settings', ['version' => 1, 'phone' => '+201012345678', 'translations' => ['ar' => ['intro' => 'تواصل معنا']]])->assertOk();

        $this->getJson('/api/v1/config', ['X-Locale' => 'ar', 'If-None-Match' => $etag])->assertOk()
            ->assertJsonPath('data.contact.phone', '+201012345678')->assertJsonPath('data.contact.intro', 'تواصل معنا');
    }

    public function test_published_terms_share_the_existing_requirements_contract(): void
    {
        $etag = $this->getJson('/api/v1/config', ['X-Locale' => 'en'])->assertOk()->headers->get('ETag');
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->admin()->create();
        $staff->givePermissionTo(['pages.manage', 'pages.publish']);
        Sanctum::actingAs($staff);
        $page = Page::query()->where('slug', 'terms')->firstOrFail();
        $draft = $this->patchJson('/api/v1/admin/pages/'.$page->id, ['draft_version' => $page->draft_version, 'translations' => [
            'en' => ['title' => 'Terms', 'content' => 'Published terms'], 'ar' => ['title' => 'الشروط', 'content' => 'الشروط المنشورة'],
        ]])->assertOk()->json('data');
        $published = $this->postJson('/api/v1/admin/pages/'.$page->id.'/publish', ['draft_version' => $draft['draft_version'], 'requires_acceptance' => true])->assertOk()->json('data');
        $requirements = $this->getJson('/api/v1/client/auth/requirements', ['X-Locale' => 'en'])->assertOk()->json('data');

        $this->getJson('/api/v1/config', ['X-Locale' => 'en', 'If-None-Match' => $etag])->assertOk()
            ->assertJsonPath('data.client_auth.requirements', $requirements)
            ->assertJsonPath('data.client_auth.requirements.terms_version', $published['terms_version']);
    }

    public function test_missing_contact_settings_do_not_prevent_application_startup(): void
    {
        ContactSetting::query()->delete();

        $this->getJson('/api/v1/config')->assertOk()->assertJsonPath('data.contact', [
            'phone' => null, 'whatsapp' => null, 'social_links' => [], 'address' => null, 'working_hours' => null, 'intro' => null,
        ]);
    }

    #[TestWith(['avatar', '/api/v1/client/profile/avatar', 'avatar'])]
    #[TestWith(['support', '/api/v1/client/media', 'file'])]
    #[TestWith(['page', '/api/v1/admin/media', 'file'])]
    public function test_advertised_upload_limits_are_enforced(string $purpose, string $endpoint, string $field): void
    {
        Storage::fake('local');
        config(['media.clamav_binary' => null, 'media.uploads.'.$purpose => ['max_size_kb' => 2, 'extensions' => ['png'], 'max_width' => 10, 'max_height' => 10]]);
        $user = User::factory()->client()->create();
        if ($purpose === 'page') {
            $this->seed(RbacSeeder::class);
            $user = User::factory()->admin()->create();
            $user->givePermissionTo('pages.manage');
        }
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/config')->assertOk()->assertJsonPath('data.uploads.'.$purpose, [
            'max_size_bytes' => 2048, 'extensions' => ['png'], 'image_max_width' => 10, 'image_max_height' => 10,
        ]);

        foreach ([UploadedFile::fake()->image('large.png', 5, 5)->size(3), UploadedFile::fake()->image('wide.png', 11, 5), UploadedFile::fake()->image('wrong.jpg', 5, 5)] as $file) {
            $this->post($endpoint, [$field => $file, 'purpose' => $purpose], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->post($endpoint, [$field => UploadedFile::fake()->image('valid.png', 10, 10)->size(2), 'purpose' => $purpose], ['Accept' => 'application/json'])->assertSuccessful();
    }
}
