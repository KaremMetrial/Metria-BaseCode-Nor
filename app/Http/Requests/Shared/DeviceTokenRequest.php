<?php

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;

final class DeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }

    public function rules(): array
    {
        return ['token' => ['required', 'string', 'min:20', 'max:4096', 'regex:/^[A-Za-z0-9_:\-]+$/']];
    }
}
