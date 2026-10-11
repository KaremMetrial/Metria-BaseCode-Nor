<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pages.manage') ?? false;
    }

    public function rules(): array
    {
        $create = $this->isMethod('post');
        $rules = [
            'slug' => $create ? ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:pages,slug'] : ['prohibited'],
            'draft_version' => $create ? ['prohibited'] : ['required', 'integer', 'min:1'],
            'media_ids' => ['sometimes', 'array', 'list', 'max:20'], 'media_ids.*' => ['required', 'uuid', 'distinct'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'show_in_footer' => ['sometimes', 'boolean'], 'show_in_settings' => ['sometimes', 'boolean'],
            'translations' => [$create ? 'required' : 'sometimes', 'array:en,ar'],
            'status' => ['prohibited'], 'published_revision_id' => ['prohibited'], 'terms_version' => ['prohibited'],
        ];
        foreach (['en', 'ar'] as $locale) {
            $rules['translations.'.$locale] = [$create && $locale === config('languages.default') ? 'required' : 'sometimes', 'array:title,content', 'required_array_keys:title,content'];
            $rules['translations.'.$locale.'.title'] = ['required_with:translations.'.$locale, 'string', 'max:255'];
            $rules['translations.'.$locale.'.content'] = ['present_with:translations.'.$locale, 'nullable', 'string', 'max:100000'];
        }

        return $rules;
    }
}
