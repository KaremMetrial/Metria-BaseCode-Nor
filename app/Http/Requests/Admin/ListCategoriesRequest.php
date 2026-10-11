<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shared\SearchCategoryRequest;

class ListCategoriesRequest extends SearchCategoryRequest
{
    public const FILTERS = parent::FILTERS + ['is_active' => ['boolean']];
}
