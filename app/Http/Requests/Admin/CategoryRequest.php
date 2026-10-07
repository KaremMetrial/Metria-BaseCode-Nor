<?php
namespace App\Http\Requests\Admin;
use App\Http\Requests\Shared\ValidatesTranslations;
use Illuminate\Foundation\Http\FormRequest;
class CategoryRequest extends FormRequest {
 use ValidatesTranslations;
 public function authorize(): bool {return $this->user()?->can($this->isMethod('post')?'categories.create':'categories.update') ?? false;}
 public function rules(): array {return array_merge(['parent_id'=>['sometimes','nullable','integer','exists:categories,id'],'is_active'=>['sometimes','boolean'],'sort_order'=>['sometimes','integer','min:0','max:65535']],$this->translationRules(['name'],['description'],!$this->isMethod('post')));}
}
