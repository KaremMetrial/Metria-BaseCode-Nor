<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Shared\UpdateProfileRequest;
use App\Services\Content\TermsService;
use Illuminate\Validation\Rule;

final class CompleteProfileRequest extends UpdateProfileRequest
{
    public function rules(): array
    {
        $terms = app(TermsService::class)->current();

        return array_merge(parent::rules(), [
            'referral_code' => ['sometimes', 'string', 'regex:/^[A-Fa-f0-9]{16}$/'],
            'name' => ['required', 'string', 'max:255'],
            'terms_accepted' => ['required', 'accepted'],
            'terms_version' => ['required', 'string', Rule::in([$terms['version']])],
            'terms_revision_id' => [Rule::requiredIf($terms['revision_id'] !== null), 'nullable', 'integer', 'min:1'],
        ]);
    }
}
