<?php

namespace App\Http\Resources\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Wallet */
final class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'currency' => $this->currency, 'balance' => $this->balance, 'is_locked' => $this->is_locked];
    }
}
