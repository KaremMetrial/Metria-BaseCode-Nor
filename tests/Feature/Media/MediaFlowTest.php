<?php

namespace Tests\Feature\Media;

use App\Jobs\PublishNotification;
use App\Models\Media;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaFlowTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions): User
    {
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->admin()->create();
        $staff->givePermissionTo($permissions);

        return $staff;
    }

    private function upload(string $actor = 'client', string $purpose = 'support'): array
    {
        return $this->post('/api/v1/'.$actor.'/media', ['purpose' => $purpose, 'file' => UploadedFile::fake()->image('photo.jpg', 40, 30)], ['Accept' => 'application/json'])->assertCreated()->json('data');
    }

    public function test_upload_is_private_reencoded_and_owned(): void
    {
        Storage::fake('local');
        $this->postJson('/api/v1/client/media')->assertUnauthorized();
        $owner = User::factory()->client()->create();
        Sanctum::actingAs($owner);
        $file = $this->upload();
        $media = Media::query()->findOrFail($file['id']);
        $this->assertSame('image/png', $file['mime_type']);
        $this->assertSame(40, $file['width']);
        $this->assertSame('ready', $file['status']);
        Storage::disk('local')->assertExists($media->path);
        $this->get($file['download_url'])->assertOk()->assertDownload()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('/api/v1/media/'.$media->id)->assertNotFound();
        $this->assertStringNotContainsString($media->path, json_encode($file));
        Sanctum::actingAs(User::factory()->client()->create());
        $this->getJson('/api/v1/client/media/'.$media->id)->assertNotFound();
        $this->getJson($file['download_url'])->assertNotFound();
        $this->deleteJson('/api/v1/client/media/'.$media->id)->assertNotFound();
    }

    public function test_validation_rejects_active_content_spoofing_dimensions_and_quota(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->client()->create());
        foreach ([UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), UploadedFile::fake()->createWithContent('fake.jpg', '<?php echo "unsafe";'), UploadedFile::fake()->image('wide.png', 4097, 1), UploadedFile::fake()->image('big.png')->size(10241)] as $file) {
            $this->post('/api/v1/client/media', ['purpose' => 'support', 'file' => $file], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->post('/api/v1/client/media', ['purpose' => 'support', 'user_id' => 99, 'file' => UploadedFile::fake()->image('photo.png')], ['Accept' => 'application/json'])->assertUnprocessable();
        config(['media.quota_bytes' => 1]);
        $this->post('/api/v1/client/media', ['purpose' => 'support', 'file' => UploadedFile::fake()->image('photo.png')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonPath('errors.file.0', __('media.quota'));
        $this->assertDatabaseCount('media', 0);
        Storage::disk('local')->assertDirectoryEmpty('media');
    }

    public function test_pdf_requires_successful_antivirus_and_failures_never_store_files(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->client()->create());
        $payload = fn () => ['purpose' => 'support', 'file' => UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF")];
        config(['media.clamav_binary' => null]);
        $this->post('/api/v1/client/media', $payload(), ['Accept' => 'application/json'])->assertStatus(503);
        config(['media.clamav_binary' => '/usr/bin/clamscan']);
        Process::preventStrayProcesses();
        Process::fake(['*' => Process::result(exitCode: 1)]);
        $this->post('/api/v1/client/media', $payload(), ['Accept' => 'application/json'])->assertUnprocessable();
        Process::assertRan(fn ($process) => $process->command[0] === '/usr/bin/clamscan');
        Process::fake(['*' => Process::result(exitCode: 2)]);
        $this->post('/api/v1/client/media', $payload(), ['Accept' => 'application/json'])->assertStatus(503);
        $this->assertDatabaseCount('media', 0);
        Process::fake(['*' => Process::result(exitCode: 0)]);
        $file = $this->post('/api/v1/client/media', $payload(), ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.mime_type', 'application/pdf')->json('data');
        $this->get($file['download_url'])->assertDownload($file['id'].'.pdf');
        $this->assertDatabaseHas('media', ['id' => $file['id'], 'inspection' => 'antivirus']);
        Process::assertRanTimes(fn ($process) => $process->command[0] === '/usr/bin/clamscan', 3);
    }

    public function test_chat_attachments_are_atomic_idempotent_and_scoped_to_participants(): void
    {
        Storage::fake('local');
        Queue::fake([PublishNotification::class]);
        $staff = $this->staff(['support.read']);
        $client = User::factory()->client()->create();
        $conversation = SupportConversation::factory()->create(['user_id' => $client->id]);
        Sanctum::actingAs($client);
        $file = $this->upload();
        $base = '/api/v1/client/support/conversations/'.$conversation->id.'/messages';
        $payload = ['client_message_id' => (string) Str::uuid(), 'media_ids' => [$file['id']]];
        $message = $this->postJson($base, $payload)->assertCreated()->assertJsonPath('data.body', '')->assertJsonPath('data.attachments.0.id', $file['id'])->json('data');
        $this->postJson($base, $payload)->assertCreated()->assertJsonPath('data.id', $message['id']);
        $this->postJson($base, array_replace($payload, ['body' => 'Changed']))->assertConflict();
        $this->postJson($base, ['client_message_id' => (string) Str::uuid(), 'media_ids' => [$file['id']]])->assertUnprocessable();
        $this->assertDatabaseCount('support_messages', 1);
        $this->getJson($base)->assertOk()->assertJsonPath('data.items.0.attachments.0.id', $file['id']);
        $this->deleteJson('/api/v1/client/media/'.$file['id'])->assertConflict();
        Queue::assertPushed(PublishNotification::class, 1);
        Sanctum::actingAs($staff);
        $this->get('/api/v1/admin/media/'.$file['id'].'/download')->assertDownload();
        $staff->revokePermissionTo('support.read');
        $this->getJson('/api/v1/admin/media/'.$file['id'].'/download')->assertNotFound();
        Sanctum::actingAs(User::factory()->client()->create());
        $this->getJson($file['download_url'])->assertNotFound();
    }

    public function test_chat_rejects_foreign_expired_and_wrong_purpose_files_without_writing_messages(): void
    {
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client);
        $conversation = SupportConversation::factory()->create(['user_id' => $client->id]);
        foreach ([Media::factory()->create(), Media::factory()->create(['user_id' => $client->id, 'expires_at' => now()->subMinute()]), Media::factory()->create(['user_id' => $client->id, 'purpose' => 'page'])] as $media) {
            $this->postJson('/api/v1/client/support/conversations/'.$conversation->id.'/messages', ['body' => 'hello', 'client_message_id' => (string) Str::uuid(), 'media_ids' => [$media->id]])->assertUnprocessable();
        }
        $this->assertDatabaseCount('support_messages', 0);
        $this->assertDatabaseCount('user_notifications', 0);
        $this->assertSame(0, $conversation->fresh()->last_message_id);
    }

    public function test_page_media_is_public_only_in_the_published_snapshot(): void
    {
        Storage::fake('local');
        $staff = $this->staff(['pages.read', 'pages.manage', 'pages.publish']);
        Sanctum::actingAs($staff);
        $file = $this->upload('admin', 'page');
        $this->getJson($file['url'])->assertNotFound();
        $page = $this->postJson('/api/v1/admin/pages', ['slug' => 'gallery', 'media_ids' => [$file['id']], 'translations' => ['en' => ['title' => 'Gallery', 'content' => '![Photo]('.$file['url'].')'], 'ar' => ['title' => 'صور', 'content' => 'صور']]])->assertCreated()->json('data');
        $base = '/api/v1/admin/pages/'.$page['id'];
        $this->getJson($file['url'])->assertNotFound();
        $this->postJson($base.'/publish', ['draft_version' => 1, 'requires_acceptance' => false])->assertOk();
        $this->get($file['url'])->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->getJson('/api/v1/pages/gallery')->assertJsonPath('data.media.0.id', $file['id']);
        $this->patchJson($base, ['draft_version' => 2, 'media_ids' => []])->assertOk();
        $this->get($file['url'])->assertOk();
        $this->postJson($base.'/publish', ['draft_version' => 3, 'requires_acceptance' => false])->assertOk();
        $this->getJson($file['url'])->assertNotFound();
        $this->getJson($base.'/revisions')->assertJsonPath('data.items.1.media_ids.0', $file['id']);
        $this->artisan('media:prune')->assertSuccessful();
        Storage::disk('local')->assertExists(Media::query()->findOrFail($file['id'])->path);
    }

    public function test_clients_and_unprivileged_admins_cannot_upload_page_media(): void
    {
        Sanctum::actingAs(User::factory()->client()->create());
        $this->postJson('/api/v1/client/media', ['purpose' => 'page'])->assertForbidden();
        $this->postJson('/api/v1/admin/media', ['purpose' => 'page'])->assertForbidden();
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson('/api/v1/admin/media', ['purpose' => 'page'])->assertForbidden();
        $this->postJson('/api/v1/admin/media', ['purpose' => 'support'])->assertForbidden();
    }

    public function test_pruning_removes_only_expired_or_discarded_unattached_uploads(): void
    {
        Storage::fake('local');
        $this->freezeTime();
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client);
        $expired = Media::factory()->create(['expires_at' => now()->subMinute()]);
        $fresh = Media::factory()->create();
        $attached = Media::factory()->create(['status' => 'attached', 'expires_at' => null, 'support_message_id' => SupportMessage::factory()]);
        $discarded = Media::factory()->create(['user_id' => $client->id]);
        foreach ([$expired, $fresh, $attached, $discarded] as $media) {
            Storage::disk('local')->put($media->path, 'file');
        }
        $this->deleteJson('/api/v1/client/media/'.$discarded->id)->assertOk();
        $this->getJson('/api/v1/client/media/'.$discarded->id)->assertNotFound();
        $this->artisan('media:prune')->assertSuccessful();
        Storage::disk('local')->assertMissing([$expired->path, $discarded->path]);
        Storage::disk('local')->assertExists([$fresh->path, $attached->path]);
        $this->assertDatabaseCount('media', 2);
    }

    public function test_avatar_replacement_revokes_old_urls_and_legacy_import_is_repeatable(): void
    {
        Storage::fake('local');
        $this->freezeTime();
        $legacyImage = UploadedFile::fake()->image('old.png');
        Storage::disk('local')->put('avatars/old.png', file_get_contents($legacyImage->getPathname()));
        $client = User::factory()->client()->create(['avatar_path' => 'avatars/old.png']);
        $this->artisan('media:import-avatars')->assertSuccessful();
        $this->artisan('media:import-avatars')->assertSuccessful();
        $this->assertDatabaseCount('media', 1);
        Sanctum::actingAs($client->fresh());
        $oldUrl = $this->getJson('/api/v1/client/profile')->json('data.avatar_url');
        $this->get($oldUrl)->assertOk();
        $newUrl = $this->post('/api/v1/client/profile/avatar', ['avatar' => UploadedFile::fake()->image('new.png')], ['Accept' => 'application/json'])->assertOk()->json('data.avatar_url');
        $this->getJson($oldUrl)->assertNotFound();
        $this->get($newUrl)->assertOk();
        $this->travel(6)->minutes();
        $this->getJson($newUrl)->assertForbidden();
        $this->deleteJson('/api/v1/client/profile/avatar')->assertOk()->assertJsonPath('data.has_avatar', false);
        $this->artisan('media:prune')->assertSuccessful();
        Storage::disk('local')->assertMissing('avatars/old.png');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_failed_deletion_keeps_a_record_for_retry(): void
    {
        $disk = Storage::fake('local');
        $media = Media::factory()->create(['status' => 'deleting']);
        $disk->put($media->path, 'file');
        $failedDisk = \Mockery::mock(FilesystemAdapter::class);
        $failedDisk->shouldReceive('delete')->once()->with($media->path)->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($failedDisk, $disk);

        $this->artisan('media:prune')->assertFailed();
        $this->assertDatabaseHas('media', ['id' => $media->id, 'status' => 'deleting']);
        $disk->assertExists($media->path);
        $this->artisan('media:prune')->assertSuccessful();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        $disk->assertMissing($media->path);
    }

    public function test_initial_chat_message_can_include_attachments_and_conflicting_retries_fail(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->client()->create());
        $file = $this->upload();
        $payload = ['request_id' => (string) Str::uuid(), 'subject' => 'Screenshot', 'client_message_id' => (string) Str::uuid(), 'media_ids' => [$file['id']]];
        $id = $this->postJson('/api/v1/client/support/conversations', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/client/support/conversations', $payload)->assertCreated()->assertJsonPath('data.id', $id);
        $this->postJson('/api/v1/client/support/conversations', array_replace($payload, ['media_ids' => [], 'body' => 'Changed']))->assertConflict();
        $this->assertDatabaseCount('support_conversations', 1);
        $this->assertDatabaseCount('support_messages', 1);
        $this->assertDatabaseHas('media', ['id' => $file['id'], 'status' => 'attached']);
    }

    public function test_removing_never_published_page_media_schedules_cleanup_and_foreign_uploads_are_rejected(): void
    {
        Storage::fake('local');
        $staff = $this->staff(['pages.read', 'pages.manage']);
        Sanctum::actingAs($staff);
        $file = $this->upload('admin', 'page');
        $foreign = Media::factory()->create(['purpose' => 'page']);
        $payload = ['slug' => 'draft-page', 'translations' => ['en' => ['title' => 'Draft', 'content' => 'Draft']], 'media_ids' => [$foreign->id]];
        $this->postJson('/api/v1/admin/pages', $payload)->assertUnprocessable();
        $this->assertDatabaseMissing('pages', ['slug' => 'draft-page']);
        $page = $this->postJson('/api/v1/admin/pages', array_replace($payload, ['media_ids' => [$file['id']]]))->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/admin/pages/'.$page, ['draft_version' => 1, 'media_ids' => []])->assertOk();
        $this->assertDatabaseHas('media', ['id' => $file['id'], 'status' => 'deleting', 'page_id' => null]);
        $this->artisan('media:prune')->assertSuccessful();
        $this->assertDatabaseMissing('media', ['id' => $file['id']]);
    }

    public function test_pending_upload_limit_rate_limit_and_inactive_accounts_are_enforced(): void
    {
        Storage::fake('local');
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client);
        config(['media.max_pending' => 1]);
        $this->upload();
        $payload = fn () => ['purpose' => 'support', 'file' => UploadedFile::fake()->image('image.png')];
        $this->post('/api/v1/client/media', $payload(), ['Accept' => 'application/json'])->assertUnprocessable();
        config(['media.uploads_per_minute' => 1]);
        $this->post('/api/v1/client/media', $payload(), ['Accept' => 'application/json'])->assertTooManyRequests();
        $client->forceFill(['status' => 'inactive'])->save();
        $this->getJson('/api/v1/client/media/'.Media::query()->firstOrFail()->id)->assertForbidden();
        $this->assertDatabaseCount('media', 1);
    }

    public function test_failed_file_write_preserves_the_avatar_and_records_cleanup_work(): void
    {
        $disk = Storage::fake('local');
        $client = User::factory()->client()->create(['avatar_path' => 'avatars/original.png']);
        $disk->put('avatars/original.png', 'original');
        Sanctum::actingAs($client);
        $failedDisk = \Mockery::mock(FilesystemAdapter::class);
        $failedDisk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($failedDisk);
        Exceptions::fake();

        $this->post('/api/v1/client/profile/avatar', ['avatar' => UploadedFile::fake()->image('new.png')], ['Accept' => 'application/json'])->assertStatus(500);
        $this->assertSame('avatars/original.png', $client->fresh()->avatar_path);
        $this->assertDatabaseHas('media', ['user_id' => $client->id, 'status' => 'deleting']);
        $disk->assertExists('avatars/original.png');
        Exceptions::assertReported(\RuntimeException::class);
    }
}
