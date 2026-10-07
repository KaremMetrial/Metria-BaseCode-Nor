<?php
namespace App\Http\Requests\Finance;
use Illuminate\Foundation\Http\FormRequest;
class RefundRequest extends FormRequest {
 public function authorize(): bool { return $this->user()?->can('payments.refund') ?? false; }
 protected function prepareForValidation(): void { $this->merge(['idempotency_key'=>$this->header('Idempotency-Key')]); }
 public function rules(): array { return ['amount'=>['required','integer','min:1','max:'.config('payments.max_amount')],'idempotency_key'=>['required','string','max:128','regex:/^[A-Za-z0-9._:-]+$/']]; }
}
