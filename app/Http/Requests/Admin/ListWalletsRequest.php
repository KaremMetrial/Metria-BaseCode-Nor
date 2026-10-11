<?php

namespace App\Http\Requests\Admin;

class ListWalletsRequest extends \App\Http\Requests\Shared\ListWalletsRequest
{
    public const DEFAULT_SORT = '-id';

    public const PAGINATED = true;

    public const FILTERS = parent::FILTERS + ['user_id' => ['integer', 'min:1'], 'is_locked' => ['boolean']];

    public const RANGES = ['balance' => 'balance'];
}
