<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShareLinkTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        $user = User::factory()->client()->create(['profile_completed_at' => now(), 'terms_accepted_at' => now(), 'terms_version' => '1']);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_public_links_preserve_targets_and_android_install_referrer_without_granting_rewards(): void
    {
        config(['links.base_url' => 'https://links.example.test', 'links.android_store_url' => 'https://play.google.com/store/apps/details?id=com.example.app', 'links.ios_store_url' => 'https://apps.apple.com/app/id123456789']);
        $user = $this->client();
        $link = $this->postJson('/api/v1/client/share-links', ['target' => ['type' => 'home']])->assertCreated()
            ->assertJsonPath('data.target', ['type' => 'home', 'id' => null, 'slug' => null])->json('data');
        $this->assertSame('https://links.example.test/l/'.$link['code'], $link['url']);
        parse_str(parse_url($link['stores']['android'], PHP_URL_QUERY), $query);
        $this->assertSame('link_code='.$link['code'], $query['referrer']);
        $this->assertSame('com.example.app', $query['id']);
        $this->assertSame('https://apps.apple.com/app/id123456789', $link['stores']['ios']);
        $this->assertDatabaseHas('referral_codes', ['user_id' => $user->id, 'code' => $link['referral_code']]);
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/links/'.$link['code'])->assertOk()->assertJsonPath('data', $link)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('referrals', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_only_public_snapshots_are_shared_and_archiving_hides_existing_links(): void
    {
        $this->client();
        $page = Page::factory()->create(['slug' => 'shared-page']);
        $this->postJson('/api/v1/client/share-links', ['target' => ['type' => 'page', 'slug' => 'shared-page']])->assertNotFound();
        $revision = PageRevision::factory()->create(['page_id' => $page->id]);
        $page->forceFill(['status' => 'published', 'published_revision_id' => $revision->id])->save();
        $link = $this->postJson('/api/v1/client/share-links', ['target' => ['type' => 'page', 'slug' => 'shared-page'], 'include_referral' => false])->assertCreated()->assertJsonPath('data.referral_code', null)->json('data');
        $this->getJson('/api/v1/links/'.$link['code'], ['X-Locale' => 'ar'])->assertOk()->assertJsonPath('data.title', 'الشروط');
        $page->forceFill(['status' => 'archived'])->save();

        $this->getJson('/api/v1/links/'.$link['code'])->assertNotFound();
        $this->assertDatabaseCount('referral_codes', 0);
    }

    public function test_hiding_a_category_ancestor_invalidates_the_shared_destination(): void
    {
        $this->client();
        $parent = new Category;
        $parent->forceFill(['is_active' => true, 'en' => ['name' => 'Parent'], 'ar' => ['name' => 'رئيسي']])->save();
        $child = new Category;
        $child->forceFill(['is_active' => true, 'parent_id' => $parent->id, 'en' => ['name' => 'Child'], 'ar' => ['name' => 'فرعي']])->save();
        $code = $this->postJson('/api/v1/client/share-links', ['target' => ['type' => 'category', 'id' => $child->id]])->assertCreated()->json('data.code');
        $parent->update(['is_active' => false]);

        $this->getJson('/api/v1/links/'.$code)->assertNotFound();
        $this->postJson('/api/v1/client/share-links', ['target' => ['type' => 'category', 'id' => $child->id]])->assertNotFound();
    }

    public function test_creation_requires_completed_client_and_rejects_arbitrary_destinations(): void
    {
        $payload = ['target' => ['type' => 'home']];
        $this->postJson('/api/v1/client/share-links', $payload)->assertUnauthorized();
        Sanctum::actingAs(User::factory()->vendor()->create());
        $this->postJson('/api/v1/client/share-links', $payload)->assertForbidden();
        Sanctum::actingAs(User::factory()->client()->create());
        $this->postJson('/api/v1/client/share-links', $payload)->assertForbidden();
        $this->client();
        foreach ([['target' => ['type' => 'url', 'url' => 'https://evil.example']], ['target' => ['type' => 'home', 'slug' => 'terms']], $payload + ['redirect_url' => 'https://evil.example'], ['target' => ['type' => 'page']]] as $invalid) {
            $this->postJson('/api/v1/client/share-links', $invalid)->assertUnprocessable();
        }
        $this->assertDatabaseCount('share_links', 0);
    }

    public function test_only_owner_can_revoke_and_deleted_owners_links_are_hidden(): void
    {
        $owner = $this->client();
        $link = ShareLink::factory()->create(['user_id' => $owner->id]);
        $this->client();
        $this->deleteJson('/api/v1/client/share-links/'.$link->code)->assertNotFound();
        Sanctum::actingAs($owner);
        $this->deleteJson('/api/v1/client/share-links/'.$link->code)->assertOk();
        $this->deleteJson('/api/v1/client/share-links/'.$link->code)->assertOk();
        $this->getJson('/api/v1/links/'.$link->code)->assertNotFound();
        $this->assertDatabaseCount('audit_logs', 1);
        $other = ShareLink::factory()->create(['user_id' => $owner->id]);
        $owner->delete();
        $this->getJson('/api/v1/links/'.$other->code)->assertNotFound();
    }

    public function test_landing_is_localized_escaped_and_handles_missing_stores_and_revoked_links(): void
    {
        $this->withoutVite();
        config(['app.name' => '<script>alert(1)</script>', 'links.android_store_url' => 'https://evil.example/download', 'links.ios_store_url' => 'javascript:alert(1)']);
        $link = ShareLink::factory()->create();
        $this->get('/l/'.$link->code.'?locale=ar')->assertOk()->assertHeader('Content-Language', 'ar')->assertSee('dir="rtl"', false)
            ->assertSee('تحميل التطبيق غير متاح حاليًا.')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false)->assertDontSee('https://evil.example', false)->assertHeader('Referrer-Policy', 'no-referrer');
        $link->forceFill(['revoked_at' => now()])->save();
        $this->get('/l/'.$link->code.'?locale=ar')->assertNotFound()->assertSee('الرابط لم يعد متاحًا');
    }

    public function test_association_files_require_real_configuration_and_return_platform_json(): void
    {
        config(['links.android_package' => null, 'links.android_fingerprints' => [], 'links.ios_app_id' => null]);
        $this->get('/.well-known/assetlinks.json')->assertNotFound();
        $this->get('/.well-known/apple-app-site-association')->assertNotFound();
        $fingerprint = implode(':', array_fill(0, 32, 'AB'));
        config(['links.android_package' => 'com.example.app', 'links.android_fingerprints' => [$fingerprint], 'links.ios_app_id' => 'ABCDEFGHIJ.com.example.app']);
        $this->getJson('/.well-known/assetlinks.json')->assertOk()->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([['relation' => ['delegate_permission/common.handle_all_urls'], 'target' => ['namespace' => 'android_app', 'package_name' => 'com.example.app', 'sha256_cert_fingerprints' => [$fingerprint]]]]);
        $this->getJson('/.well-known/apple-app-site-association')->assertOk()->assertExactJson(['applinks' => ['apps' => [], 'details' => [['appID' => 'ABCDEFGHIJ.com.example.app', 'paths' => ['/l/*']]]]]);
        config(['links.android_fingerprints' => ['invalid']]);
        $this->getJson('/.well-known/assetlinks.json')->assertNotFound();
    }
}
