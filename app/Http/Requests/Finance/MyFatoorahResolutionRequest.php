<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MyFatoorahResolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() && $this->user()->isActive() && $this->user()->can('myfatoorah.manage');
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in(['confirmed_succeeded', 'confirmed_failed'])],
            'provider_reference' => ['required_if:outcome,confirmed_succeeded', 'nullable', 'string', 'max:255'],
            'evidence' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
