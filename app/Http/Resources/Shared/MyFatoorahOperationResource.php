<?php

namespace App\Http\Resources\Shared;

use App\Models\MyFatoorahOperation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MyFatoorahOperation */
final class MyFatoorahOperationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'uuid' => $this->uuid, 'actor_id' => $this->actor_id, 'customer_id' => $this->customer_id,
            'operation' => $this->operation, 'status' => $this->status, 'result' => $this->result,
            'created_at' => $this->created_at->toIso8601String(), 'updated_at' => $this->updated_at->toIso8601String()];
    }
}
