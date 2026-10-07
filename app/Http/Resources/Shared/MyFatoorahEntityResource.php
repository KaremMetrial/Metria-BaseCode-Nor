<?php

namespace App\Http\Resources\Shared;

use App\Models\MyFatoorahEntity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MyFatoorahEntity */
final class MyFatoorahEntityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'kind' => $this->kind, 'reference' => $this->reference, 'customer_id' => $this->customer_id,
            'status' => $this->status, 'needs_refresh' => $this->needs_refresh, 'snapshot' => $this->snapshot, 'last_event' => $this->last_event,
            'synced_at' => $this->synced_at?->toIso8601String(), 'created_at' => $this->created_at->toIso8601String()];
    }
}
