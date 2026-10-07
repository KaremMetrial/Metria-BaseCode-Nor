<?php

namespace Database\Seeders;

use App\Enums\GovernorateType;
use App\Models\Country;
use App\Models\Governorate;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the first-level divisions of the operating markets.
 *
 * Idempotent on `(country_id, code)` -- the same pair the unique index uses, so
 * re-running updates names in place instead of duplicating divisions or breaking
 * the ids that addresses will point at.
 *
 * The local word for the division is looked up from the country rather than
 * stored per row (App\Enums\GovernorateType), so a verified emirate cannot be
 * labelled a governorate.
 *
 * Run CountrySeeder first: this seeder refuses to invent the countries its rows
 * attach to.
 */
class GovernorateSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, list<array{code: string, en: string, ar: string}>> $data */
        $data = require database_path('data/governorates.php');

        /** @var list<string> $locales */
        $locales = array_keys(config('languages.supported', []));

        foreach ($data as $iso2 => $governorates) {
            $country = Country::query()->iso2($iso2)->first();

            if ($country === null) {
                // Thrown rather than skipped: silently seeding zero divisions for
                // a country listed in database/data/governorates.php looks like a
                // data problem for as long as it takes someone to trace it back
                // to a seeder that ran out of order.
                throw new RuntimeException(
                    "CountrySeeder must run before GovernorateSeeder: [{$iso2}] is missing."
                );
            }

            $type = GovernorateType::forCountry($iso2);

            foreach ($governorates as $index => $governorate) {
                $model = Governorate::query()->firstOrNew([
                    'country_id' => $country->getKey(),
                    'code' => $governorate['code'],
                ]);

                $model->fill([
                    'country_id' => $country->getKey(),
                    'code' => $governorate['code'],
                    // Null when the country is not in the map, which is truthful:
                    // the API simply omits the label rather than calling an
                    // unknown division by the wrong name.
                    'type' => $type,
                    'is_active' => $model->exists ? $model->is_active : true,
                    // The data file is ordered alphabetically by English name, so
                    // position is a sensible default display order an
                    // administrator can override later.
                    'sort_order' => $index,
                ]);

                $model->fill([
                    'translations' => $this->translations($governorate, $locales),
                ]);

                $model->save();
            }
        }
    }

    /**
     * @param  array{code: string, en: string, ar: string}  $governorate
     * @param  list<string>  $locales
     * @return array<string, array{name: string}>
     */
    private function translations(array $governorate, array $locales): array
    {
        $translations = [];

        foreach ($locales as $locale) {
            if (isset($governorate[$locale])) {
                $translations[$locale] = ['name' => $governorate[$locale]];
            }
        }

        return $translations;
    }
}
