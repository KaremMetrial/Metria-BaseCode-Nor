<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Validator;

class ListConversationsRequest extends \App\Http\Requests\Shared\ListConversationsRequest
{
    public const FILTERS = parent::FILTERS + ['user_id' => ['integer', 'min:1'], 'assigned_to' => ['integer', 'min:1']];

    public const NULLS = ['unassigned' => 'assigned_to'];

    protected function validateFilters(Validator $validator): void
    {
        if ($this->boolean('unassigned') && $this->has('assigned_to')) {
            $validator->errors()->add('unassigned', __('filters.conflict'));
        }
    }
}
