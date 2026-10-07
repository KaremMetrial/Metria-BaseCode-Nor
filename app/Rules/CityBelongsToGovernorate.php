<?php

namespace App\Rules;

use App\Models\City;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Asserts that a city actually belongs to the submitted governorate.
 *
 * The deepest link in the location chain: verifying country -> governorate and
 * governorate -> city separately is what makes an address trustworthy, because
 * no single field's existence implies the others' agreement.
 */
final class CityBelongsToGovernorate implements DataAwareRule, ValidationRule
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
        $governorateId = $this->data['governorate_id'] ?? null;

        if ($governorateId === null || $governorateId === '' || $value === null || $value === '') {
            return;
        }

        $belongs = City::query()
            ->whereKey($value)
            ->where('governorate_id', $governorateId)
            ->exists();

        if (! $belongs) {
            $fail(__('locations.hierarchy.city_not_in_governorate'));
        }
    }
}
