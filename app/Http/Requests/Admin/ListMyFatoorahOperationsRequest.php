<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shared\ListRequest;

class ListMyFatoorahOperationsRequest extends ListRequest
{
    public const SORTS = ['id', 'created_at', 'updated_at'];

    public const FILTERS = ['operation' => ['string', 'max:100'], 'status' => ['in:ready,sending,succeeded,rejected,uncertain,confirmed_succeeded,confirmed_failed'], 'customer_id' => ['integer', 'min:1'], 'actor_id' => ['integer', 'min:1']];

    public const MULTI = ['status'];
}
