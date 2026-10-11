<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Shared\SupportMessageRequest;

final class SupportConversationRequest extends SupportMessageRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['request_id' => ['required', 'uuid'], 'subject' => ['required', 'string', 'max:200'], 'assigned_to' => ['prohibited'], 'status' => ['prohibited']]);
    }
}
