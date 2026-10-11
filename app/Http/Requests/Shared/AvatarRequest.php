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
        $limits = config('media.uploads.avatar');

        return ['avatar' => ['required', 'image', 'mimes:'.implode(',', $limits['extensions']), 'max:'.$limits['max_size_kb'], 'dimensions:max_width='.$limits['max_width'].',max_height='.$limits['max_height']]];
    }
}
