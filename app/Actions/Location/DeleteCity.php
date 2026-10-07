<?php

namespace App\Actions\Location;

use App\Models\City;

/**
 * Deletes a city.
 *
 * This is the only location row with no dependants. Later phases will add
 * addresses and orders that point here; those constraints will be added as
 * `restrictOnDelete` guards (surfaced through ResourceInUseException) rather
 * than by loosening this action.
 */
final class DeleteCity
{
    public function execute(City $city): void
    {
        $city->delete();
    }
}
