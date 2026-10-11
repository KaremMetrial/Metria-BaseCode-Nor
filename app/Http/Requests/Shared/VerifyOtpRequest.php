<?php

namespace App\Http\Requests\Shared;

class VerifyOtpRequest extends OtpRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['device_name' => ['sometimes', 'string', 'max:100'], 'device_id' => ['sometimes', 'uuid'], 'challenge_id' => ['required', 'uuid'], 'code' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);
    }
}
