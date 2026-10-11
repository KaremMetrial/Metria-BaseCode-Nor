<?php

namespace App\Http\Resources\Client;

use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Referral */
final class ReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'status' => 'registered', 'created_at' => $this->created_at->toIso8601String()];
    }
}
