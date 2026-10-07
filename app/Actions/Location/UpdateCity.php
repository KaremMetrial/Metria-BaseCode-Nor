<?php

namespace App\Actions\Location;

use App\Actions\Location\Concerns\TranslatableWrite;
use App\Models\City;

/**
 * Applies a (possibly partial) update to a city and its translations.
 *
 * Coordinates are nullable on purpose: a real place without a surveyed centroid
 * exists, and defaulting to 0,0 would place it in the Gulf of Guinea. A caller
 * that wants to clear them can send an explicit null.
 */
final class UpdateCity
{
    use TranslatableWrite;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(City $city, array $data): City
    {
        return $this->persist($city, $data);
    }
}
