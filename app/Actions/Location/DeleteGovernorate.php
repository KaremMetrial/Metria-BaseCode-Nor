<?php

namespace App\Actions\Location;

use App\Exceptions\Conflict\ResourceInUseException;
use App\Models\Governorate;

/**
 * Deletes a governorate, refusing while it still has cities.
 *
 * The `cities.governorate_id` FK is cascadeOnDelete, so deleting would otherwise
 * silently take every city with it. Refusing keeps the operator aware that
 * removing a division is a restructuring step, not a harmless cleanup.
 */
final class DeleteGovernorate
{
    public function execute(Governorate $governorate): void
    {
        if ($governorate->cities()->exists()) {
            throw ResourceInUseException::make(
                "Governorate {$governorate->getKey()} still has cities."
            );
        }

        $governorate->delete();
    }
}
