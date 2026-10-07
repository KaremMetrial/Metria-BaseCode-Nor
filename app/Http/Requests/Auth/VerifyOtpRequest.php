<?php

namespace App\Http\Requests\Auth;

class VerifyOtpRequest extends OtpRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['challenge_id' => ['required', 'uuid'], 'code' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);
    }
}
