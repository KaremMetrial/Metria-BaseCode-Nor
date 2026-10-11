<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shared\ListRequest;

class ListContactMessagesRequest extends ListRequest
{
    public const SORTS = ['id', 'created_at', 'updated_at'];

    public const SEARCH = ['reference' => 'exact', 'subject' => 'like', 'phone' => 'exact'];

    public const FILTERS = ['status' => ['in:new,reviewed,closed']];

    public const MULTI = ['status'];
}
