<?php

namespace App\Actions\Location;

use App\Actions\Location\Concerns\TranslatableWrite;
use App\Models\Country;

/**
 * Creates a country together with its translations.
 *
 * An Action rather than controller code so an importer, an artisan command and
 * the admin endpoint all create countries the same way, including the
 * transaction and translation handling.
 */
final class CreateCountry
{
    use TranslatableWrite;

    /**
     * @param  array<string, mixed>  $data  Validated payload; may carry a `translations` wrapper.
     */
    public function execute(array $data): Country
    {
        return $this->persist(new Country, $data);
    }
}
