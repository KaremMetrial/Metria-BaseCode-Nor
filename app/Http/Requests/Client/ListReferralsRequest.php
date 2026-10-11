<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Shared\ListRequest;

class ListReferralsRequest extends ListRequest
{
    public const FILTERS = ['id' => ['integer', 'min:1']];
}
