<?php

namespace App\Http\Requests\Shared;

class ListConversationsRequest extends ListRequest
{
    public const SORTS = ['id', 'created_at', 'updated_at'];

    public const DEFAULT_SORT = '-updated_at,-id';

    public const SEARCH = ['subject' => 'like'];

    public const FILTERS = ['status' => ['in:new,in_progress,resolved,closed']];

    public const MULTI = ['status'];
}
