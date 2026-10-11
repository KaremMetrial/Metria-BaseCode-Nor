<?php

namespace App\Actions\Shared\Support;

use App\Actions\Shared\Media\ManageMedia;
use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Jobs\PublishNotification;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageConversation
{
    public function __construct(private readonly NotificationOutbox $notifications, private readonly AuditLoggerInterface $audit, private readonly ManageMedia $media) {}

    /** @return Builder<SupportConversation> */
    public function query(User $actor): Builder
    {
        $staff = $actor->isAdmin();
        $query = SupportConversation::query();
        if (! $staff) {
            $query->where('user_id', $actor->id);
        }

        return $query->withCount(['messages as unread_count' => fn ($q) => $q->where('sender_type', $staff ? 'client' : 'admin')->whereColumn('support_messages.id', '>', 'support_conversations.'.($staff ? 'staff_read_through' : 'client_read_through'))]);
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): SupportConversation
    {
        return DB::transaction(function () use ($actor, $data): SupportConversation {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $hashData = [$data['subject'], $data['body'] ?? '', $data['client_message_id']];
            if (! empty($data['media_ids'])) {
                $hashData[] = $this->mediaIds($data);
            }
            $hash = hash('sha256', json_encode($hashData, JSON_THROW_ON_ERROR));
            $existing = SupportConversation::query()->where('user_id', $actor->id)->where('request_id', $data['request_id'])->first();
            if ($existing) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw new DomainException(ErrorCode::IDEMPOTENCY_CONFLICT);
                }

                return $this->query($actor)->findOrFail($existing->id);
            }
            if (SupportConversation::query()->where('user_id', $actor->id)->whereIn('status', ['new', 'in_progress'])->count() >= 5) {
                throw new DomainException(ErrorCode::RESOURCE_CONFLICT);
            }
            $conversation = new SupportConversation;
            $conversation->forceFill(['user_id' => $actor->id, 'subject' => $data['subject'], 'request_id' => $data['request_id'], 'request_hash' => $hash])->save();
            $this->send($actor, $conversation->id, $data);

            return $this->query($actor)->findOrFail($conversation->id);
        }, 5);
    }

    /** @param array<string, mixed> $data */
    public function send(User $actor, int $conversationId, array $data): SupportMessage
    {
        return DB::transaction(function () use ($actor, $conversationId, $data): SupportMessage {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $conversation = $this->locked($actor, $conversationId);
            $existing = SupportMessage::query()->where('sender_id', $actor->id)->where('client_message_id', $data['client_message_id'])->first();
            if ($existing) {
                if ($existing->support_conversation_id !== $conversation->id || $existing->body !== ($data['body'] ?? '') || $existing->attachments()->orderBy('id')->pluck('id')->all() !== $this->mediaIds($data)) {
                    throw new DomainException(ErrorCode::IDEMPOTENCY_CONFLICT);
                }

                return $existing->load('attachments');
            }
            if ($conversation->status === 'closed') {
                throw new DomainException(ErrorCode::RESOURCE_CONFLICT);
            }
            $message = new SupportMessage;
            $message->forceFill(['support_conversation_id' => $conversation->id, 'sender_id' => $actor->id, 'sender_type' => $actor->isAdmin() ? 'admin' : 'client', 'client_message_id' => $data['client_message_id'], 'body' => $data['body'] ?? '', 'created_at' => now()])->save();
            $this->media->attachMessage($actor, $message, $this->mediaIds($data));
            $conversation->forceFill(['last_message_id' => $message->id, 'version' => $conversation->version + 1,
                'status' => $actor->isAdmin() || $conversation->status === 'resolved' ? 'in_progress' : $conversation->status])->save();
            $this->notify($conversation, $actor, 'support-message:'.$message->id, 'notifications.support_message');

            return $message->load('attachments');
        }, 5);
    }

    public function read(User $actor, int $conversationId, int $throughId): SupportConversation
    {
        return DB::transaction(function () use ($actor, $conversationId, $throughId): SupportConversation {
            $conversation = $this->locked($actor, $conversationId);
            if ($throughId !== 0 && ! $conversation->messages()->whereKey($throughId)->exists()) {
                throw ValidationException::withMessages(['through_message_id' => [__('content.invalid_read_marker')]]);
            }
            $field = $actor->isAdmin() ? 'staff_read_through' : 'client_read_through';
            if ($throughId > $conversation->getAttribute($field)) {
                $conversation->forceFill([$field => $throughId, 'version' => $conversation->version + 1])->save();
            }

            return $this->query($actor)->findOrFail($conversation->id);
        }, 5);
    }

    public function status(User $actor, int $conversationId, string $status, int $version): SupportConversation
    {
        return DB::transaction(function () use ($actor, $conversationId, $status, $version): SupportConversation {
            $conversation = $this->locked($actor, $conversationId);
            if ($conversation->version !== $version) {
                throw new DomainException(ErrorCode::RESOURCE_CONFLICT);
            }
            if ($conversation->status !== $status) {
                $conversation->forceFill(['status' => $status, 'version' => $conversation->version + 1])->save();
                $this->audit->record(new AuditEntry(AuditAction::SUPPORT_STATUS_CHANGED, $conversation, actor: $actor, context: ['status' => $status]));
                $this->notify($conversation, $actor, 'support-status:'.$conversation->id.':'.$conversation->version, 'notifications.support_status');
            }

            return $this->query($actor)->findOrFail($conversation->id);
        }, 5);
    }

    public function assign(User $actor, int $conversationId, ?int $assigneeId, int $version): SupportConversation
    {
        return DB::transaction(function () use ($actor, $conversationId, $assigneeId, $version): SupportConversation {
            $conversation = $this->locked($actor, $conversationId);
            if ($conversation->version !== $version) {
                throw new DomainException(ErrorCode::RESOURCE_CONFLICT);
            }
            if ($assigneeId !== null) {
                $staff = User::query()->find($assigneeId);
                if (! $staff || ! $staff->isAdmin() || ! $staff->isActive() || ! $staff->can('support.read') || ! $staff->can('support.reply')) {
                    throw ValidationException::withMessages(['assigned_to' => [__('content.invalid_assignee')]]);
                }
            }
            $conversation->forceFill(['assigned_to' => $assigneeId, 'version' => $conversation->version + 1])->save();
            $this->audit->record(new AuditEntry(AuditAction::SUPPORT_ASSIGNED, $conversation, actor: $actor, context: ['assigned_to' => $assigneeId]));

            return $this->query($actor)->findOrFail($conversation->id);
        }, 5);
    }

    /** @param array<string, mixed> $data
     * @return list<string>
     */
    private function mediaIds(array $data): array
    {
        $ids = $data['media_ids'] ?? [];
        sort($ids, SORT_STRING);

        return $ids;
    }

    private function locked(User $actor, int $id): SupportConversation
    {
        $query = SupportConversation::query()->whereKey($id);
        if (! $actor->isAdmin()) {
            $query->where('user_id', $actor->id);
        }

        return $query->lockForUpdate()->firstOrFail();
    }

    private function notify(SupportConversation $conversation, User $sender, string $eventKey, string $translation): void
    {
        if ($sender->isAdmin()) {
            $recipients = User::query()->whereKey($conversation->user_id)->where('status', 'active')->get();
        } else {
            $recipients = User::query()->where('type', 'admin')->where('status', 'active')->get()->filter(fn (User $user): bool => $user->can('support.read'));
            if ($conversation->assigned_to && $recipients->contains('id', $conversation->assigned_to)) {
                $recipients = $recipients->where('id', $conversation->assigned_to);
            }
        }
        foreach ($recipients->sortBy('id') as $recipient) {
            $key = $eventKey.':'.$recipient->id;
            $this->notifications->record($recipient, $translation, [], $key, 'transactional', ['screen' => 'support_conversation', 'conversation_id' => $conversation->id, 'last_message_id' => $conversation->last_message_id]);
            $notification = UserNotification::query()->where('event_key', $key)->firstOrFail();
            PublishNotification::dispatch($notification->id)->afterCommit();
        }
    }
}
