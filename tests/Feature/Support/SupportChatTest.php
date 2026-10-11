<?php

namespace Tests\Feature\Support;

use App\Jobs\PublishNotification;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions = ['support.read', 'support.reply', 'support.manage']): User
    {
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->admin()->create();
        $staff->givePermissionTo($permissions);

        return $staff;
    }

    private function message(string $body = 'I need help'): array
    {
        return ['client_message_id' => (string) Str::uuid(), 'body' => $body];
    }

    public function test_chat_persists_messages_and_emits_generic_notifications_without_duplicates(): void
    {
        Queue::fake([PublishNotification::class]);
        $staff = $this->staff();
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client);
        $payload = ['subject' => 'Account access', 'request_id' => (string) Str::uuid()] + $this->message('Private customer detail');
        $conversation = $this->postJson('/api/v1/client/support/conversations', $payload)->assertCreated()->json('data');
        $id = $conversation['id'];
        $this->postJson('/api/v1/client/support/conversations', $payload)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('support_messages', 1);
        $this->assertDatabaseCount('user_notifications', 1);
        Sanctum::actingAs($staff);
        $reply = $this->message('A private staff reply');
        $response = $this->postJson('/api/v1/admin/support/conversations/'.$id.'/messages', $reply)->assertCreated()->assertJsonPath('data.sender_type', 'admin');
        $this->postJson('/api/v1/admin/support/conversations/'.$id.'/messages', $reply)->assertCreated()->assertJsonPath('data.id', $response->json('data.id'));
        $this->assertDatabaseCount('support_messages', 2);
        $this->assertDatabaseCount('user_notifications', 2);
        $notification = UserNotification::query()->where('user_id', $client->id)->firstOrFail();
        $this->assertSame('support_conversation', $notification->action['screen']);
        $this->assertSame($id, $notification->action['conversation_id']);
        $this->assertSame('A new support message is available.', $notification->payload()['body']);
        Queue::assertPushed(PublishNotification::class, 2);
    }

    public function test_clients_cannot_access_other_conversations_or_spoof_senders(): void
    {
        $owner = User::factory()->client()->create();
        $conversation = SupportConversation::factory()->create(['user_id' => $owner->id]);
        $this->getJson('/api/v1/client/support/conversations')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->client()->create());
        $base = '/api/v1/client/support/conversations/'.$conversation->id;
        $this->getJson($base)->assertNotFound();
        $this->getJson($base.'/messages')->assertNotFound();
        $this->postJson($base.'/messages', $this->message())->assertNotFound();
        $this->patchJson($base.'/read', ['through_message_id' => 0])->assertNotFound();
        $this->postJson($base.'/close', ['version' => 1])->assertNotFound();
        Sanctum::actingAs($owner);
        $this->postJson($base.'/messages', $this->message() + ['sender_type' => 'admin'])->assertUnprocessable();
        $this->assertDatabaseCount('support_messages', 0);
    }

    public function test_staff_permissions_are_required_for_reply_assignment_and_status(): void
    {
        $staff = $this->staff(['support.read']);
        $conversation = SupportConversation::factory()->create();
        Sanctum::actingAs($staff);
        $base = '/api/v1/admin/support/conversations/'.$conversation->id;
        $this->getJson($base)->assertOk();
        $this->postJson($base.'/messages', $this->message())->assertForbidden();
        $this->patchJson($base.'/assignment', ['version' => 1, 'assigned_to' => $staff->id])->assertForbidden();
        $this->patchJson($base.'/status', ['version' => 1, 'status' => 'closed'])->assertForbidden();
        Sanctum::actingAs(User::factory()->vendor()->create());
        $this->getJson('/api/v1/client/support/conversations')->assertForbidden();
    }

    public function test_read_markers_are_monotonic_and_preserve_later_unread_messages(): void
    {
        $client = User::factory()->client()->create();
        $staff = User::factory()->admin()->create();
        $conversation = SupportConversation::factory()->create(['user_id' => $client->id]);
        $first = SupportMessage::factory()->create(['support_conversation_id' => $conversation->id, 'sender_id' => $staff->id, 'sender_type' => 'admin']);
        $second = SupportMessage::factory()->create(['support_conversation_id' => $conversation->id, 'sender_id' => $staff->id, 'sender_type' => 'admin']);
        $other = SupportMessage::factory()->create();
        Sanctum::actingAs($client);
        $base = '/api/v1/client/support/conversations/'.$conversation->id;
        $this->getJson($base)->assertOk()->assertJsonPath('data.unread_count', 2);
        $this->patchJson($base.'/read', ['through_message_id' => $first->id])->assertOk()->assertJsonPath('data.unread_count', 1);
        $this->patchJson($base.'/read', ['through_message_id' => 0])->assertOk()->assertJsonPath('data.client_read_through', $first->id);
        $this->patchJson($base.'/read', ['through_message_id' => $other->id])->assertUnprocessable();
        $this->patchJson($base.'/read', ['through_message_id' => $second->id])->assertOk()->assertJsonPath('data.unread_count', 0);
    }

    public function test_pagination_recovers_missed_messages_without_cross_conversation_leaks(): void
    {
        $client = User::factory()->client()->create();
        $conversation = SupportConversation::factory()->create(['user_id' => $client->id]);
        $messages = SupportMessage::factory()->count(3)->create(['support_conversation_id' => $conversation->id, 'sender_id' => $client->id]);
        SupportMessage::factory()->create();
        Sanctum::actingAs($client);
        $base = '/api/v1/client/support/conversations/'.$conversation->id.'/messages';
        $this->getJson($base.'?per_page=2')->assertOk()->assertJsonCount(2, 'data.items')->assertJsonPath('data.meta.has_more', true)->assertJsonPath('data.meta.next_after_id', $messages[1]->id);
        $this->getJson($base.'?after_id='.$messages[1]->id)->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $messages[2]->id)->assertJsonPath('data.meta.has_more', false);
    }

    public function test_conflicting_ids_are_rejected_and_closed_chat_blocks_new_messages(): void
    {
        Queue::fake([PublishNotification::class]);
        $staff = $this->staff();
        $client = User::factory()->client()->create();
        $conversation = SupportConversation::factory()->create(['user_id' => $client->id]);
        Sanctum::actingAs($client);
        $base = '/api/v1/client/support/conversations/'.$conversation->id;
        $payload = $this->message();
        $this->postJson($base.'/messages', $payload)->assertCreated();
        $this->postJson($base.'/messages', array_replace($payload, ['body' => 'Changed']))->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        $version = $this->getJson($base)->json('data.version');
        $this->postJson($base.'/close', ['version' => $version])->assertOk()->assertJsonPath('data.status', 'closed');
        $this->postJson($base.'/messages', $this->message())->assertConflict();
        $this->postJson($base.'/messages', $payload)->assertCreated();
        Sanctum::actingAs($staff);
        $this->patchJson('/api/v1/admin/support/conversations/'.$conversation->id.'/status', ['version' => $version + 1, 'status' => 'resolved'])->assertOk();
        Sanctum::actingAs($client);
        $this->postJson($base.'/messages', $this->message())->assertCreated();
        $this->getJson($base)->assertJsonPath('data.status', 'in_progress');
        Queue::assertPushed(PublishNotification::class);
    }

    public function test_assignment_validates_staff_and_rejects_stale_version(): void
    {
        $staff = $this->staff();
        $client = User::factory()->client()->create();
        $conversation = SupportConversation::factory()->create(['user_id' => $client->id]);
        Sanctum::actingAs($staff);
        $base = '/api/v1/admin/support/conversations/'.$conversation->id;
        $this->patchJson($base.'/assignment', ['version' => 1, 'assigned_to' => $client->id])->assertUnprocessable();
        $this->patchJson($base.'/assignment', ['version' => 1, 'assigned_to' => $staff->id])->assertOk()->assertJsonPath('data.assigned_to', $staff->id);
        $this->patchJson($base.'/assignment', ['version' => 1, 'assigned_to' => null])->assertConflict();
        $this->assertDatabaseHas('audit_logs', ['action' => 'support.assigned']);
    }

    public function test_open_conversation_limit_and_creation_idempotency(): void
    {
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client);
        $data = ['request_id' => (string) Str::uuid(), 'subject' => 'Help'] + $this->message();
        $this->postJson('/api/v1/client/support/conversations', $data)->assertCreated();
        $this->postJson('/api/v1/client/support/conversations', array_replace($data, ['subject' => 'Changed']))->assertConflict();
        SupportConversation::factory()->count(4)->create(['user_id' => $client->id]);
        $this->postJson('/api/v1/client/support/conversations', ['request_id' => (string) Str::uuid(), 'subject' => 'Sixth'] + $this->message())->assertConflict();
        $this->assertDatabaseCount('support_conversations', 5);
    }
}
