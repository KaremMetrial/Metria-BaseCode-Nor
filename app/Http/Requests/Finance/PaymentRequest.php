<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key'), 'provider' => $this->input('provider', config('payments.default'))]);
    }

    public function rules(): array
    {
        return ['amount' => ['required', 'integer', 'min:1', 'max:'.config('payments.max_amount')], 'currency' => ['required', Rule::in(config('payments.currencies'))], 'provider' => ['required', 'string', 'max:40', Rule::in(array_keys(config('payments.providers')))], 'idempotency_key' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/'], 'status' => ['prohibited'], 'user_id' => ['prohibited'], 'wallet_id' => ['prohibited']];
    }
}
