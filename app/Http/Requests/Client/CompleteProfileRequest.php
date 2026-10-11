<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Shared\UpdateProfileRequest;
use Illuminate\Validation\Rule;

final class CompleteProfileRequest extends UpdateProfileRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'name' => ['required', 'string', 'max:255'],
            'terms_accepted' => ['required', 'accepted'],
            'terms_version' => ['required', 'string', Rule::in([(string) config('client.terms_version')])],
        ]);
    }
}
