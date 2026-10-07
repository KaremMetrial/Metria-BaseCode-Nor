<?php

namespace App\Rules;

use App\Models\Governorate;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Asserts that a governorate actually belongs to the submitted country.
 *
 * Without this, a client can send any pair of ids that each exist in isolation
 * -- the "country = Egypt, governorate = Dubai" case. Existence checks on the
 * two fields independently cannot catch that; only a relational check can.
 *
 * `DataAwareRule` gives access to the whole payload so the two fields can be
 * compared.
 */
final class GovernorateBelongsToCountry implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $countryId = $this->data['country_id'] ?? null;

        // Absence is the job of `required`/`exists`; this rule only judges
        // relationships, so it must not double-report a missing value.
        if ($countryId === null || $countryId === '' || $value === null || $value === '') {
            return;
        }

        $belongs = Governorate::query()
            ->whereKey($value)
            ->where('country_id', $countryId)
            ->exists();

        if (! $belongs) {
            $fail(__('locations.hierarchy.governorate_not_in_country'));
        }
    }
}
