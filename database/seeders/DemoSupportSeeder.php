<?php

namespace Database\Seeders;

use App\Models\ContactMessage;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Str;

final class DemoSupportSeeder extends DevelopmentSeeder
{
    protected function seed(): void
    {
        $client = $this->user('client');
        $staff = $this->user('support');
        foreach (['new', 'reviewed', 'closed'] as $index => $status) {
            ContactMessage::query()->firstOrCreate(['request_id' => '10000000-0000-4000-8000-00000000000'.($index + 1)], [
                'reference' => (string) Str::uuid(), 'request_hash' => hash('sha256', 'demo:contact:'.$status),
                'user_id' => $index === 0 ? null : $client->id, 'name' => 'Demo contact '.$status,
                'phone' => $client->phone, 'subject' => 'استفسار تجريبي '.$status,
                'message' => 'رسالة تجريبية: أريد معرفة طريقة استخدام التطبيق.', 'status' => $status,
            ]);
        }
        foreach (['new', 'in_progress', 'resolved', 'closed'] as $index => $status) {
            $conversation = SupportConversation::query()->firstOrCreate([
                'user_id' => $client->id, 'request_id' => '20000000-0000-4000-8000-00000000000'.($index + 1),
            ], ['request_hash' => hash('sha256', 'demo:support:'.$status), 'subject' => 'مساعدة تجريبية '.$status,
                'status' => $status, 'assigned_to' => $index === 0 ? null : $staff->id]);
            if (! $conversation->wasRecentlyCreated) {
                continue;
            }
            $first = SupportMessage::query()->create(['support_conversation_id' => $conversation->id,
                'sender_id' => $client->id, 'sender_type' => 'client', 'client_message_id' => (string) Str::uuid(),
                'body' => 'أحتاج مساعدة في استخدام المحفظة.', 'created_at' => now()->subMinutes(10)]);
            $last = $first;
            if ($status !== 'new') {
                $last = SupportMessage::query()->create(['support_conversation_id' => $conversation->id,
                    'sender_id' => $staff->id, 'sender_type' => 'admin', 'client_message_id' => (string) Str::uuid(),
                    'body' => 'أهلًا بك، يمكنك متابعة الحركات من صفحة المحفظة.', 'created_at' => now()->subMinutes(5)]);
            }
            $conversation->forceFill(['last_message_id' => $last->id, 'client_read_through' => in_array($status, ['resolved', 'closed'], true) ? $last->id : $first->id,
                'staff_read_through' => $status === 'new' ? 0 : $last->id, 'version' => $status === 'new' ? 2 : 3])->save();
        }
    }
}
