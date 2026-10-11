<?php

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;

class SupportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }

    public function rules(): array
    {
        return ['client_message_id' => ['required', 'uuid'], 'body' => ['required_without:media_ids', 'nullable', 'string', 'max:5000'], 'media_ids' => ['sometimes', 'array', 'list', 'max:5'], 'media_ids.*' => ['required', 'uuid', 'distinct'],
            'sender_id' => ['prohibited'], 'sender_type' => ['prohibited'], 'user_id' => ['prohibited']];
    }
}
