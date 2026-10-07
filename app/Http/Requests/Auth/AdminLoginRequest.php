<?php
namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;
class AdminLoginRequest extends FormRequest {
 public function authorize(): bool { return true; }
 public function rules(): array { return ['email'=>['required','string','email','max:255'],'password'=>['required','string','max:255']]; }
}
