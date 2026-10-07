<?php
namespace App\Http\Requests\Profile;
use Illuminate\Foundation\Http\FormRequest;
class UpdateProfileRequest extends FormRequest {
 public function authorize(): bool { return true; }
 public function rules(): array { return ['name'=>['sometimes','required','string','max:255'],'locale'=>['sometimes','required',\Illuminate\Validation\Rule::in(['en','ar'])],'timezone'=>['sometimes','nullable','timezone'],'phone'=>['prohibited'],'phone_country_id'=>['prohibited'],'type'=>['prohibited'],'status'=>['prohibited'],'roles'=>['prohibited'],'permissions'=>['prohibited'],'balance'=>['prohibited'],'phone_verified_at'=>['prohibited'],'password'=>['prohibited'],'email'=>['prohibited']]; }
}
