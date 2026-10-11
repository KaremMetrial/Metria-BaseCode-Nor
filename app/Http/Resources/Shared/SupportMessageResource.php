<?php

namespace App\Http\Resources\Shared;

use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupportMessage */
final class SupportMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'conversation_id' => $this->support_conversation_id, 'sender_type' => $this->sender_type,
            'is_mine' => $this->sender_id === $request->user()?->id, 'client_message_id' => $this->client_message_id,
            'body' => $this->body, 'attachments' => MediaResource::collection($this->whenLoaded('attachments')), 'created_at' => $this->created_at->toIso8601String()];
    }
}
