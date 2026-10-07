<?php

namespace App\Http\Resources\Shared;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A country as a client sees it.
 *
 * Only the active locale is exposed (spec section 40). Every field is listed
 * explicitly rather than returning the model, so a column added later is never
 * published by accident -- relying on `toArray()` is how internal metadata
 * leaks.
 *
 * @mixin Country
 */
class CountryResource extends JsonResource
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

            // Localized for the active request locale by LocaleMiddleware.
            'name' => $this->name,
            'nationality' => $this->nationality,

            'calling_code' => $this->calling_code,
            'currency_code' => $this->currency_code,

            // A UI hint for phone inputs only. Clients must still send the raw
            // number and let the server normalise it; the authoritative
            // numbering plan lives in libphonenumber.
            'phone_example' => $this->phone_example,
        ];
    }
}
