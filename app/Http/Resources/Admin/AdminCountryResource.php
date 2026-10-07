<?php

namespace App\Http\Resources\Admin;

use App\Models\Country;
use App\Models\CountryTranslation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A country as an administrator sees it.
 *
 * Differs from the client resource in two ways (spec section 60):
 *  - administrative fields (is_active, sort_order, timestamps) are included;
 *  - ALL translations are returned, keyed by locale, because the edit form has
 *    to populate every locale at once.
 *
 * `whenLoaded` is deliberate: if a caller forgets to eager-load translations,
 * the key is omitted rather than firing a query per row.
 *
 * @mixin Country
 */
class AdminCountryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iso2' => $this->iso2,
            'iso3' => $this->iso3,
            'numeric_code' => $this->numeric_code,
            'calling_code' => $this->calling_code,
            'currency_code' => $this->currency_code,
            'timezone_default' => $this->timezone_default,
            'phone_example' => $this->phone_example,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,

            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->mapWithKeys(fn (CountryTranslation $translation) => [
                    $translation->locale => [
                        'name' => $translation->name,
                        'nationality' => $translation->nationality,
                    ],
                ])
                ->all()),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
