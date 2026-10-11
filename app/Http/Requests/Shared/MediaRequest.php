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
        $page = $this->input('purpose') === 'page';
        $rules = ['required', 'file', 'mimes:'.($page ? 'jpg,jpeg,png,webp' : 'jpg,jpeg,png,webp,pdf'), 'extensions:'.($page ? 'jpg,jpeg,png,webp' : 'jpg,jpeg,png,webp,pdf'), 'max:'.($page ? 5120 : 10240)];
        if ($this->file('file')?->getMimeType() !== 'application/pdf') {
            $rules[] = 'image';
            $rules[] = 'dimensions:max_width=4096,max_height=4096';
        }

        return ['purpose' => ['required', 'string', 'in:page,support'], 'file' => $rules,
            'user_id' => ['prohibited'], 'disk' => ['prohibited'], 'path' => ['prohibited'], 'status' => ['prohibited']];
    }
}
