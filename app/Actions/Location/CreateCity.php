<?php

namespace App\Actions\Location;

use App\Actions\Location\Concerns\TranslatableWrite;
use App\Models\City;

/**
 * Creates a city together with its translations.
 *
 * Note what is *not* accepted here: a country id. A city reaches its country
 * through its governorate, and accepting a redundant country would let a caller
 * store a contradiction (Egypt -> Dubai). The governorate is the only geographic
 * parent.
 */
final class CreateCity
{
    use TranslatableWrite;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): City
    {
        return $this->persist(new City, $data);
    }
}
