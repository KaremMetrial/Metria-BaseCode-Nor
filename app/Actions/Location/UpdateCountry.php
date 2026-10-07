<?php

namespace App\Actions\Location;

use App\Actions\Location\Concerns\TranslatableWrite;
use App\Models\Country;

/**
 * Applies a (possibly partial) update to a country and its translations.
 *
 * Field-level partiality is not implemented here: `fill()` already ignores keys
 * that are absent from the payload, and the Update request only ever validates
 * keys the client actually sent.
 */
final class UpdateCountry
{
    use TranslatableWrite;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Country $country, array $data): Country
    {
        return $this->persist($country, $data);
    }
}
