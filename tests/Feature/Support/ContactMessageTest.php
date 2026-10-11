<?php

namespace Tests\Feature\Support;

use App\Models\ContactMessage;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactMessageTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['request_id' => (string) Str::uuid(), 'name' => 'Ahmed', 'country_id' => Country::factory()->withIso2('EG')->create()->id, 'phone' => '01012345678', 'subject' => 'Lost access', 'message' => 'Please help me contact support.'];
    }

    public function test_guests_can_send_without_email_and_retries_create_one_message(): void
    {
        $data = $this->payload();
        $reference = $this->postJson('/api/v1/contact-messages', $data)->assertCreated()->assertJsonMissingPath('data.phone')->json('data.reference');
        $this->postJson('/api/v1/contact-messages', $data)->assertCreated()->assertJsonPath('data.reference', $reference);
        $this->postJson('/api/v1/contact-messages', array_replace($data, ['message' => 'Different']))->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        $this->assertDatabaseCount('contact_messages', 1);
        $this->assertDatabaseHas('contact_messages', ['phone' => '+201012345678', 'user_id' => null, 'status' => 'new']);
    }

    public function test_validation_and_rate_limits_are_enforced(): void
    {
        $this->postJson('/api/v1/contact-messages', [])->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
        $data = $this->payload();
        $this->postJson('/api/v1/contact-messages', $data + ['user_id' => 1])->assertUnprocessable();
        $this->postJson('/api/v1/contact-messages', $data)->assertCreated();
        $this->postJson('/api/v1/contact-messages', $data)->assertTooManyRequests();
    }

    public function test_contact_association_uses_token_and_rejects_invalid_bearer(): void
    {
        $data = $this->payload();
        $this->withToken('invalid')->postJson('/api/v1/contact-messages', $data)->assertUnauthorized();
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/contact-messages', $data)->assertCreated();
        $this->assertDatabaseHas('contact_messages', ['user_id' => $user->id]);
    }

    public function test_only_authorized_staff_can_read_and_process_submissions(): void
    {
        $this->seed(RbacSeeder::class);
        $message = ContactMessage::factory()->create();
        $this->getJson('/api/v1/admin/contact-messages')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->client()->create());
        $this->getJson('/api/v1/admin/contact-messages/'.$message->id)->assertForbidden();
        $staff = User::factory()->admin()->create();
        $staff->givePermissionTo('contact.read');
        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/admin/contact-messages?status=new')->assertOk()->assertJsonCount(1, 'data.items');
        $this->patchJson('/api/v1/admin/contact-messages/'.$message->id.'/status', ['status' => 'reviewed'])->assertForbidden();
        $staff->givePermissionTo('contact.manage');
        $this->patchJson('/api/v1/admin/contact-messages/'.$message->id.'/status', ['status' => 'reviewed'])->assertOk()->assertJsonPath('data.status', 'reviewed');
        $this->assertDatabaseHas('audit_logs', ['action' => 'contact.reviewed']);
    }

    public function test_settings_are_localized_versioned_and_reject_unsafe_links(): void
    {
        $this->getJson('/api/v1/contact-settings')->assertOk()->assertJsonPath('data.phone', null);
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->admin()->create();
        $staff->givePermissionTo('contact.settings');
        Sanctum::actingAs($staff);
        $data = ['version' => 1, 'phone' => '+201012345678', 'translations' => ['en' => ['intro' => 'Contact us'], 'ar' => ['intro' => 'تواصل معنا']], 'social_links' => [['label' => 'Website', 'url' => 'https://example.com']]];
        $this->patchJson('/api/v1/admin/contact-settings', $data)->assertOk()->assertJsonPath('data.version', 2);
        $this->getJson('/api/v1/contact-settings', ['X-Locale' => 'ar'])->assertOk()->assertJsonPath('data.intro', 'تواصل معنا');
        $this->patchJson('/api/v1/admin/contact-settings', $data)->assertConflict();
        $this->patchJson('/api/v1/admin/contact-settings', ['version' => 2, 'social_links' => [['label' => 'Bad', 'url' => 'javascript:alert(1)']]])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/contact-settings', ['version' => 2, 'social_links' => []])->assertOk();
        $this->getJson('/api/v1/contact-settings')->assertOk()->assertJsonPath('data.social_links', []);
    }
}
