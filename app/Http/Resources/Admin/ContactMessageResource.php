<?php

namespace App\Http\Resources\Admin;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContactMessage */
final class ContactMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'reference' => $this->reference, 'user_id' => $this->user_id, 'name' => $this->name,
            'phone' => $this->phone, 'subject' => $this->subject, 'message' => $this->message, 'status' => $this->status,
            'created_at' => $this->created_at->toIso8601String(), 'updated_at' => $this->updated_at->toIso8601String()];
    }
}
