<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shared\ListRequest;

class ListMyFatoorahEntitiesRequest extends ListRequest
{
    public const NULLABLE_SORTS = ['synced_at'];

    public const SORTS = ['id', 'created_at', 'updated_at', 'synced_at'];

    public const SEARCH = ['reference' => 'exact'];

    public const FILTERS = ['kind' => ['string', 'max:40'], 'status' => ['string', 'max:60'], 'reference' => ['string', 'max:255'], 'customer_id' => ['integer', 'min:1'], 'needs_refresh' => ['boolean']];

    public const DATES = ['created' => 'created_at', 'synced' => 'synced_at'];
}
