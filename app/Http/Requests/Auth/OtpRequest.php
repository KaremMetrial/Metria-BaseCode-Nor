<?php
namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;
class OtpRequest extends FormRequest {
 public function authorize(): bool { return true; }
 public function rules(): array { return ['country_id'=>['required','integer','exists:countries,id'],'phone'=>['required','string','max:40']]; }
}
