<?php

namespace App\Http\Resources\Shared;

use App\Models\SupportConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupportConversation */
final class SupportConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'subject' => $this->subject, 'status' => $this->status, 'version' => $this->version,
            'user_id' => $this->when($request->user()?->isAdmin(), $this->user_id),
            'assigned_to' => $this->when($request->user()?->isAdmin(), $this->assigned_to),
            'last_message_id' => $this->last_message_id, 'client_read_through' => $this->client_read_through,
            'staff_read_through' => $this->staff_read_through, 'unread_count' => (int) ($this->unread_count ?? 0),
            'created_at' => $this->created_at->toIso8601String(), 'updated_at' => $this->updated_at->toIso8601String()];
    }
}
