<?php

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;

final class NotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }

    public function rules(): array
    {
        return ['preferences' => ['required', 'array:security_push,transactional_push,marketing_push'],
            'preferences.security_push' => ['sometimes', 'boolean'],
            'preferences.transactional_push' => ['sometimes', 'boolean'],
            'preferences.marketing_push' => ['sometimes', 'boolean']];
    }
}
