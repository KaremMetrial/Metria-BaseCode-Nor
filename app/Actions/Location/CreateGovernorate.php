<?php

namespace App\Actions\Location;

use App\Actions\Location\Concerns\TranslatableWrite;
use App\Models\Governorate;

/**
 * Creates a governorate (or state, province, emirate, region -- see
 * App\Enums\GovernorateType) together with its translations.
 *
 * The hierarchy is already guaranteed: the Store request rejects a country id
 * that does not exist, and the FK enforces it at the database as well.
 */
final class CreateGovernorate
{
    use TranslatableWrite;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Governorate
    {
        return $this->persist(new Governorate, $data);
    }
}
