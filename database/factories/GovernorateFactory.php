<?php

namespace Database\Factories;

use App\Enums\GovernorateType;
use App\Models\Country;
use App\Models\Governorate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Governorate>
 */
class GovernorateFactory extends Factory
{
    protected $model = Governorate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // A parent country by default, because a governorate cannot exist
            // without one. Tests that care about the hierarchy pass an explicit
            // `country_id` or use forCountry().
            'country_id' => Country::factory(),
            'code' => null,
            'type' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Governorate $governorate): void {
            if (! $governorate->hasTranslation('en')) {
                $this->translate($governorate, 'en', (string) fake()->unique()->city());
            }
        });
    }

    public function forCountry(Country $country): static
    {
        return $this->state(fn (): array => ['country_id' => $country->getKey(), 'type' => GovernorateType::forCountry($country->iso2)]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function withCode(string $code): static
    {
        return $this->state(fn (): array => ['code' => strtoupper($code)]);
    }

    public function ofType(GovernorateType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }

    public function withArabicName(?string $name = null): static
    {
        return $this->afterCreating(function (Governorate $governorate) use ($name): void {
            $this->translate($governorate, 'ar', $name ?? (string) fake()->unique()->city());
        });
    }

    private function translate(Governorate $governorate, string $locale, string $name): void
    {
        $governorate->translateOrNew($locale)->fill(['name' => $name]);
        $governorate->save();
    }
}
