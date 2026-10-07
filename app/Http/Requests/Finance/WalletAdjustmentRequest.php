<?php

namespace App\Http\Requests\Finance;

use Illuminate\Validation\Rule;

class WalletAdjustmentRequest extends RefundRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('wallets.adjust') ?? false) && in_array($this->input('direction'), ['credit', 'debit'], true) && $this->user()->can('wallets.'.$this->input('direction'));
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), ['direction' => ['required', Rule::in(['credit', 'debit'])], 'reason' => ['required', 'string', 'max:255']]);
    }
}
