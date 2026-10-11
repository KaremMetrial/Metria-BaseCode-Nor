<?php

namespace App\Http\Requests\Shared;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;

final class MediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor?->isActive()) {
            return false;
        }
        if ($this->input('purpose') === 'page') {
            return $actor->isAdmin() && $actor->can('pages.manage');
        }

        return ($actor->type === UserType::CLIENT) || ($actor->isAdmin() && $actor->can('support.read') && $actor->can('support.reply'));
    }

    public function rules(): array
    {
        $limits = config('media.uploads.'.($this->input('purpose') === 'page' ? 'page' : 'support'));
        $extensions = implode(',', $limits['extensions']);
        $rules = ['required', 'file', 'mimes:'.$extensions, 'extensions:'.$extensions, 'max:'.$limits['max_size_kb']];
        if ($this->file('file')?->getMimeType() !== 'application/pdf') {
            $rules[] = 'image';
            $rules[] = 'dimensions:max_width='.$limits['max_width'].',max_height='.$limits['max_height'];
        }

        return ['purpose' => ['required', 'string', 'in:page,support'], 'file' => $rules,
            'user_id' => ['prohibited'], 'disk' => ['prohibited'], 'path' => ['prohibited'], 'status' => ['prohibited']];
    }
}
