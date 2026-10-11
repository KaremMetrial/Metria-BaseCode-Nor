<?php

namespace App\Http\Requests\Shared;

class ListWalletsRequest extends ListRequest
{
    public const SORTS = ['id', 'currency', 'balance'];

    public const DEFAULT_SORT = 'currency';

    public const PAGINATED = false;

    public const FILTERS = ['currency' => ['string', 'regex:/^[A-Z]{3}$/D']];

    public const DATES = [];

    public const CURRENCY_RANGE = 'balance';
}
