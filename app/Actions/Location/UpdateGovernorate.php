<?php

namespace App\Actions\Location;

use App\Actions\Location\Concerns\TranslatableWrite;
use App\Models\Governorate;

/**
 * Applies a (possibly partial) update to a governorate and its translations.
 *
 * Moving a governorate to a different country is allowed but deliberately not
 * silent: the request re-scopes the `code` uniqueness check to the *new*
 * country, so a move that would collide with an existing code is rejected
 * instead of being merged into a database constraint error.
 */
final class UpdateGovernorate
{
    use TranslatableWrite;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Governorate $governorate, array $data): Governorate
    {
        return $this->persist($governorate, $data);
    }
}
