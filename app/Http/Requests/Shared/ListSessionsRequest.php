<?php

namespace App\Http\Requests\Shared;

class ListSessionsRequest extends ListRequest
{
    public const NULLABLE_SORTS = ['last_used_at'];

    public const SORTS = ['id', 'created_at', 'last_used_at'];

    public const PAGINATED = false;

    public const SEARCH = ['name' => 'like'];

    public const VIRTUAL_FILTERS = ['is_current'];

    public const FILTERS = ['is_current' => ['boolean']];

    public const DATES = ['created' => 'created_at', 'last_used' => 'last_used_at'];
}
