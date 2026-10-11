<?php

namespace App\Http\Requests\Shared;

class ListCountriesRequest extends ListRequest
{
    public const NULLABLE_SORTS = ['iso3'];

    public const SORTS = ['id', 'sort_order', 'name', 'iso2', 'iso3', 'calling_code'];

    public const DEFAULT_SORT = 'sort_order,iso2';

    public const PAGINATED = false;

    public const TRANSLATED = 'name';

    public const SEARCH = ['iso2' => 'exact', 'iso3' => 'exact', 'calling_code' => 'exact'];

    public const FILTERS = ['iso2' => ['string', 'regex:/^[A-Z]{2}$/D'], 'calling_code' => ['string', 'max:8']];

    public const DATES = [];
}
