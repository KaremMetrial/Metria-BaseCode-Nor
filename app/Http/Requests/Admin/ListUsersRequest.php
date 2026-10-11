<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shared\ListRequest;

class ListUsersRequest extends ListRequest
{
    public const NULLABLE_SORTS = ['last_login_at'];

    public const SORTS = ['id', 'name', 'created_at', 'last_login_at'];

    public const SEARCH = ['name' => 'like', 'phone' => 'exact', 'email' => 'like'];

    public const FILTERS = ['type' => ['in:admin,client,vendor'], 'status' => ['in:pending,active,suspended,blocked,inactive'], 'phone_country_id' => ['integer', 'min:1']];

    public const MULTI = ['type', 'status'];

    public const NOT_NULLS = ['phone_verified' => 'phone_verified_at'];
}
