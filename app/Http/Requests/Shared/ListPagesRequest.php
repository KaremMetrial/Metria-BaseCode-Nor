<?php

namespace App\Http\Requests\Shared;

class ListPagesRequest extends ListRequest
{
    public const SORTS = ['id', 'sort_order', 'title', 'slug', 'published_at'];

    public const DEFAULT_SORT = 'sort_order,id';

    public const PAGINATED = false;

    public const SEARCH = ['title' => 'like', 'slug' => 'like'];

    public const FILTERS = ['slug' => ['string', 'max:100'], 'show_in_footer' => ['boolean'], 'show_in_settings' => ['boolean']];

    public const DATES = [];
}
