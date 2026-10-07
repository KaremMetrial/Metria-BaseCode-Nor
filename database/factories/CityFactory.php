<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Governorate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    protected $model = City::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // A city's only geographic parent. Note there is no `country_id`:
            // a factory that could set one would let a test build a state the
            // database and the models both forbid.
            'governorate_id' => Governorate::factory(),
            'code' => null,
            'postal_code' => null,
            'latitude' => null,
            'longitude' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (City $city): void {
            if (! $city->hasTranslation('en')) {
                $this->translate($city, 'en', (string) fake()->unique()->city());
            }
        });
    }

    public function forGovernorate(Governorate $governorate): static
    {
        return $this->state(fn (): array => ['governorate_id' => $governorate->getKey()]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function withCode(string $code): static
    {
        return $this->state(fn (): array => ['code' => strtoupper($code)]);
    }

    /**
     * Coordinates are floats in the model and null by default, so a test that
     * needs a mappable city asks for one explicitly.
     */
    public function atCoordinates(float $latitude, float $longitude): static
    {
        return $this->state(fn (): array => [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    public function withArabicName(?string $name = null): static
    {
        return $this->afterCreating(function (City $city) use ($name): void {
            $this->translate($city, 'ar', $name ?? (string) fake()->unique()->city());
        });
    }

    private function translate(City $city, string $locale, string $name): void
    {
        $city->translateOrNew($locale)->fill(['name' => $name]);
        $city->save();
    }
}
