<?php

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;

final class AvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }

    public function rules(): array
    {
        return ['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048']];
    }
}
