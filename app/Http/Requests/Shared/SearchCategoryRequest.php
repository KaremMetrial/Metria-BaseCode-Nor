<?php

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['keyword' => ['sometimes', 'string', 'max:100'], 'parent_id' => ['sometimes', 'nullable', 'integer', 'min:1'], 'sort' => ['sometimes', Rule::in(['sort_order', 'id'])], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}
