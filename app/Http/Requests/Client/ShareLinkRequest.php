<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

final class ShareLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasCompletedProfile() ?? false;
    }

    public function rules(): array
    {
        return [
            'target' => ['required', 'array:type,id,slug'],
            'target.type' => ['required', 'string', 'in:home,page,category'],
            'target.id' => ['required_if:target.type,category', 'prohibited_unless:target.type,category', 'integer', 'min:1'],
            'target.slug' => ['required_if:target.type,page', 'prohibited_unless:target.type,page', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'include_referral' => ['sometimes', 'boolean'],
            'redirect_url' => ['prohibited'], 'url' => ['prohibited'], 'user_id' => ['prohibited'], 'referral_code' => ['prohibited'],
        ];
    }
}
