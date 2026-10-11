<?php

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;

final class ContactMessageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->routeIs('contact.store') && in_array($this->input('locale'), ['en', 'ar'], true)) {
            app()->setLocale($this->input('locale'));
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['request_id' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:255'],
            'country_id' => ['required', 'integer', 'exists:countries,id'], 'phone' => ['required', 'string', 'max:40'],
            'subject' => ['required', 'string', 'max:200'], 'message' => ['required', 'string', 'max:5000'],
            'user_id' => ['prohibited'], 'status' => ['prohibited']];
    }
}
