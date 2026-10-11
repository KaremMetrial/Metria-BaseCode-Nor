<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ContactSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contact.settings') ?? false;
    }

    public function rules(): array
    {
        $rules = ['version' => ['required', 'integer', 'min:1'], 'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+[1-9][0-9]{6,14}$/'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'regex:/^\+[1-9][0-9]{6,14}$/'],
            'social_links' => ['sometimes', 'array', 'max:10'], 'social_links.*' => ['array:label,url', 'required_array_keys:label,url'],
            'social_links.*.label' => ['required', 'string', 'max:100'], 'social_links.*.url' => ['required', 'url:http,https', 'max:2048'],
            'translations' => ['sometimes', 'array:en,ar']];
        foreach (['en', 'ar'] as $locale) {
            $rules['translations.'.$locale] = ['sometimes', 'array:intro,address,working_hours'];
            foreach (['intro', 'address', 'working_hours'] as $field) {
                $rules['translations.'.$locale.'.'.$field] = ['sometimes', 'nullable', 'string', 'max:2000'];
            }
        }

        return $rules;
    }
}
