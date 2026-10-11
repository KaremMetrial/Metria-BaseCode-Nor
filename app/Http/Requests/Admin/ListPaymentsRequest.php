<?php

namespace App\Http\Requests\Admin;

class ListPaymentsRequest extends \App\Http\Requests\Shared\ListPaymentsRequest
{
    public const FILTERS = parent::FILTERS + ['user_id' => ['integer', 'min:1']];
}
