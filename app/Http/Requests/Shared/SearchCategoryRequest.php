<?php

namespace App\Http\Requests\Shared;

use Illuminate\Validation\Validator;

class SearchCategoryRequest extends ListRequest
{
    public const SORTS = ['id', 'sort_order', 'name'];

    public const DEFAULT_SORT = 'sort_order,id';

    public const TRANSLATED = 'name';

    public const VIRTUAL_FILTERS = ['keyword'];

    public const FILTERS = ['keyword' => ['string', 'min:1', 'max:100'], 'parent_id' => ['nullable', 'integer', 'min:1']];

    public const NULLS = ['root_only' => 'parent_id'];

    public const DATES = [];

    protected function validateFilters(Validator $validator): void
    {
        if ($this->has(['search', 'keyword']) && $this->input('search') !== $this->input('keyword')) {
            $validator->errors()->add('keyword', __('filters.conflict'));
        }
        if ($this->has('root_only') && $this->has('parent_id') && $this->boolean('root_only') !== ($this->input('parent_id') === null)) {
            $validator->errors()->add('root_only', __('filters.conflict'));
        }
    }
}
