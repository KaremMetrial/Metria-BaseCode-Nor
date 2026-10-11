<?php

namespace App\Http\Requests\Shared;

class ListSupportMessagesRequest extends ListRequest
{
    public const DATES = [];

    public function rules(): array
    {
        return ['after_id' => ['sometimes', 'integer', 'min:0'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}
