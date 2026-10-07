<?php

namespace App\Http\Requests\Admin;

/**
 * PATCH /admin/countries/{country}
 *
 * Extends the store request purely to reuse its rules with `partial: true`.
 * Inheriting guarantees store and update can never validate the same field
 * differently -- a drift that is invisible until a client is rejected on one
 * endpoint and accepted on the other.
 */
class UpdateCountryRequest extends StoreCountryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->countryRules(partial: true);
    }
}
