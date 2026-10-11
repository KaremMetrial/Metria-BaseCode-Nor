<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shared\ListRequest;

class ListPageRevisionsRequest extends ListRequest
{
    public const SORTS = ['id', 'version', 'published_at'];

    public const DEFAULT_SORT = '-version';

    public const FILTERS = ['version' => ['integer', 'min:1'], 'published_by' => ['integer', 'min:1']];

    public const DATES = ['published' => 'published_at'];
}
