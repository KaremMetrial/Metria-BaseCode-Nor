<?php

namespace App\Http\Requests\Admin;

class ListCountriesRequest extends \App\Http\Requests\Shared\ListCountriesRequest
{
    public const FILTERS = parent::FILTERS + ['is_active' => ['boolean']];
}
