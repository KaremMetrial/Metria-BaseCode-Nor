<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shared\ListRequest;

class ListPagesRequest extends ListRequest
{
    public const SORTS = ['id', 'sort_order', 'created_at', 'updated_at', 'title', 'slug'];

    public const DEFAULT_SORT = 'sort_order,id';

    public const SEARCH = ['slug' => 'like'];

    public const TRANSLATED = 'title';

    public const FILTERS = ['status' => ['in:draft,published,archived'], 'show_in_footer' => ['boolean'], 'show_in_settings' => ['boolean']];

    public const MULTI = ['status'];
}
