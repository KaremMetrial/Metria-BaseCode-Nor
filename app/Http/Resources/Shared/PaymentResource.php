<?php

namespace App\Http\Resources\Shared;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
final class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'uuid' => $this->uuid, 'provider' => $this->provider, 'amount' => $this->amount, 'currency' => $this->currency, 'status' => $this->status->value, 'refunded_amount' => $this->refunded_amount, 'client_secret' => $this->when($request->user()?->id === $this->user_id && in_array($this->status, [PaymentStatus::PENDING, PaymentStatus::PROCESSING], true), fn () => $this->client_secret), 'payment_url' => $this->when($this->provider === 'myfatoorah' && $request->user()?->id === $this->user_id && in_array($this->status, [PaymentStatus::PENDING, PaymentStatus::PROCESSING], true), fn () => $this->payment_url), 'created_at' => $this->created_at->toIso8601String()];
    }
}
