<?php

namespace App\Http\Resources\Shared;

use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'uuid' => $this->uuid, 'provider' => $this->provider, 'amount' => $this->amount, 'currency' => $this->currency, 'status' => $this->status->value, 'refunded_amount' => $this->refunded_amount, 'client_secret' => $this->when($request->user()?->id === $this->user_id && in_array($this->status, [PaymentStatus::PENDING, PaymentStatus::PROCESSING], true), fn () => $this->client_secret), 'created_at' => $this->created_at->toIso8601String()];
    }
}
