<?php

namespace Database\Factories;

use App\Models\Country;
use Giggsey\Locale\Locale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // A real ISO 3166-1 alpha-2 code: the tests that reach for
            // `Country` most often need a plausible region for the
            // phone-number flow, and "XY" would not survive libphonenumber.
            'iso2' => strtoupper((string) fake()->unique()->countryCode()),
            'iso3' => null,
            'numeric_code' => null,
            'calling_code' => '+'.fake()->numberBetween(1, 999),
            'currency_code' => null,
            'timezone_default' => null,
            'phone_example' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function configure(): static
    {
        // Every country gets an English translation, because English is the
        // default locale and a country with no name in it is not a valid row --
        // the API resource would render null and a form would show a blank.
        return $this->afterCreating(function (Country $country): void {
            if (! $country->hasTranslation('en')) {
                $this->translate($country, 'en', Locale::getDisplayRegion('en-'.$country->iso2, 'en'));
            }
        });
    }

    /**
     * A country that is switched off. Used to prove deactivated reference data
     * disappears from public endpoints without disappearing from the admin ones.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function withIso2(string $iso2): static
    {
        return $this->state(fn (): array => ['iso2' => strtoupper($iso2)]);
    }

    /**
     * Adds the second locale, so fallback behaviour can be tested rather than
     * assumed.
     */
    public function withArabicName(?string $name = null): static
    {
        return $this->afterCreating(function (Country $country) use ($name): void {
            $this->translate($country, 'ar', $name ?? Locale::getDisplayRegion('en-'.$country->iso2, 'en'));
        });
    }

    /**
     * Writes a translation through Astrotomic's own path.
     *
     * `translateOrNew` is what the application uses in production, so the factory
     * cannot drift from it -- and it sets `locale` and the foreign key directly
     * (neither is fillable, deliberately).
     *
     * `save()` fires even when the row itself is not dirty, which is what
     * triggers Astrotomic's `saved` hook and persists the translation.
     */
    private function translate(Country $country, string $locale, string $name): void
    {
        $country->translateOrNew($locale)->fill(['name' => $name]);
        $country->save();
    }
}
