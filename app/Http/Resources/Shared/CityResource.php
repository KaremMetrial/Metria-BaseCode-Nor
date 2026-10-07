<?php

namespace App\Http\Resources\Shared;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A city as a client sees it.
 *
 * Coordinates are included because clients use them for map centring. When the
 * country is needed, callers eager-load `governorate.country` -- there is no
 * direct relation from a city to its country (see the City model).
 *
 * @mixin City
 */
class CityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'governorate_id' => $this->governorate_id,
            'name' => $this->name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
