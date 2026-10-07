<?php

namespace App\Http\Resources\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'direction' => $this->direction->value, 'amount' => $this->amount, 'balance_after' => $this->balance_after, 'reason' => $this->reason, 'created_at' => $this->created_at->toIso8601String()];
    }
}
