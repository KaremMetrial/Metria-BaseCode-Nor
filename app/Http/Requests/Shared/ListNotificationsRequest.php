<?php

namespace App\Http\Requests\Shared;

use Illuminate\Validation\Validator;

class ListNotificationsRequest extends ListRequest
{
    public const DEFAULT_SORT = '-created_at,-id';

    public function rules(): array
    {
        return parent::rules() + [
            'status' => ['sometimes', 'in:read,unread,all'],
            'category' => ['sometimes', 'in:security,transactional,marketing'],
            'since_version' => ['sometimes', 'integer', 'min:0'],
            'until_version' => ['sometimes', 'integer', 'min:0'],
            'cursor' => ['sometimes', 'string', 'max:2048'],
        ];
    }

    protected function validateFilters(Validator $validator): void
    {
        if ($this->has('since_version')) {
            foreach (['status', 'category', 'sort', 'sort_type', 'page', 'created_from', 'created_to'] as $field) {
                if ($this->has($field)) {
                    $validator->errors()->add($field, __('filters.sync'));
                }
            }
            if ($this->has('until_version') && $this->input('until_version') < $this->input('since_version')) {
                $validator->errors()->add('until_version', __('filters.range'));
            }
        } elseif ($this->hasAny(['until_version', 'cursor'])) {
            $validator->errors()->add('since_version', __('filters.sync'));
        }
    }
}
