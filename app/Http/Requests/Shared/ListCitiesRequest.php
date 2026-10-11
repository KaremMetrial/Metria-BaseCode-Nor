<?php

namespace App\Http\Requests\Shared;

class ListCitiesRequest extends ListRequest
{
    public const NULLABLE_SORTS = ['code'];

    public const SORTS = ['id', 'sort_order', 'name', 'code'];

    public const DEFAULT_SORT = 'sort_order,id';

    public const PAGINATED = false;

    public const TRANSLATED = 'name';

    public const SEARCH = ['code' => 'exact', 'postal_code' => 'exact'];

    public const FILTERS = ['code' => ['string', 'max:32'], 'postal_code' => ['string', 'max:32']];

    public const DATES = [];
}
