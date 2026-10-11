<?php

namespace App\Http\Requests\Shared;

class ListPaymentsRequest extends ListRequest
{
    public const SORTS = ['id', 'created_at', 'amount'];

    public const FILTERS = ['status' => ['in:pending,processing,paid,failed,partially_refunded,refunded'], 'provider' => ['in:stripe,myfatoorah'], 'currency' => ['string', 'regex:/^[A-Z]{3}$/D']];

    public const MULTI = ['status'];

    public const RANGES = ['amount' => 'amount'];

    public const CURRENCY_RANGE = 'amount';
}
