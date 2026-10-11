<?php

namespace App\Http\Requests\Shared;

class ListGovernoratesRequest extends ListRequest
{
    public const NULLABLE_SORTS = ['code'];

    public const SORTS = ['id', 'sort_order', 'name', 'code'];

    public const DEFAULT_SORT = 'sort_order,id';

    public const PAGINATED = false;

    public const TRANSLATED = 'name';

    public const SEARCH = ['code' => 'exact'];

    public const FILTERS = ['code' => ['string', 'max:32'], 'type' => ['in:governorate,state,province,emirate,region']];

    public const DATES = [];
}
