<?php

namespace App\Actions\Location;

use App\Exceptions\Conflict\ResourceInUseException;
use App\Models\Country;
use App\Models\User;

/**
 * Deletes a country, but only when nothing depends on it.
 *
 * The guards exist because deleting reference data silently cascades:
 * `users.phone_country_id` is `restrictOnDelete` (so a referenced country would
 * surface as a raw QueryException -- a 500 -- instead of a clear 409), while
 * governorates and cities cascade. Refusing while children exist forces the
 * operator to be explicit about the blast radius rather than discovering it.
 *
 * Reference data is normally retired by setting `is_active = false`; this
 * endpoint exists for rows created by mistake.
 */
final class DeleteCountry
{
    public function execute(Country $country): void
    {
        if (User::withTrashed()->where('phone_country_id', $country->getKey())->exists()) {
            throw ResourceInUseException::make(
                "Country {$country->getKey()} is referenced by users."
            );
        }

        if ($country->governorates()->exists()) {
            throw ResourceInUseException::make(
                "Country {$country->getKey()} still has governorates."
            );
        }

        // Translations are removed by the database: country_translations.country_id
        // is cascadeOnDelete.
        $country->delete();
    }
}
