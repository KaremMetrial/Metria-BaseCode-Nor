<?php

namespace App\Http\Resources\Shared;

use App\Models\Governorate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A governorate as a client sees it.
 *
 * `type` is the real local division word (governorate/state/province/emirate/
 * region) so a client can label the picker correctly instead of calling
 * everything a "governorate".
 *
 * @mixin Governorate
 */
class GovernorateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_id' => $this->country_id,
            'name' => $this->name,
            'type' => $this->type?->value,
        ];
    }
}
