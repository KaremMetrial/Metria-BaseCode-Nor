<?php

namespace App\Http\Resources\Admin;

use App\Models\City;
use App\Models\CityTranslation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A city as an administrator sees it (all translations, admin fields).
 *
 * @mixin City
 */
class AdminCityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'governorate_id' => $this->governorate_id,
            'code' => $this->code,
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,

            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->mapWithKeys(fn (CityTranslation $translation) => [
                    $translation->locale => ['name' => $translation->name],
                ])
                ->all()),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
