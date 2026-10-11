<?php

namespace App\Http\Requests\Shared;

class ListWalletTransactionsRequest extends ListRequest
{
    public const SORTS = ['id', 'created_at', 'amount'];

    public const FILTERS = ['direction' => ['in:credit,debit'], 'reason' => ['string', 'max:255']];

    public const RANGES = ['amount' => 'amount'];
}
