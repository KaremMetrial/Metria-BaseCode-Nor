<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Governorate;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Seeds cities by permanent internal code, independent of translated names. */
class CitySeeder extends Seeder
{
    /**
     * Sort positions are spaced out so a city can be inserted between two seeded
     * ones without renumbering the list.
     */
    private const SORT_STEP = 10;

    public function run(): void
    {
        /** @var array<string, list<array{en: string, ar: string, lat?: float, lng?: float}>> $data */
        $data = require database_path('data/cities.php');

        /** @var list<string> $locales */
        $locales = array_keys(config('languages.supported', []));

        foreach ($data as $governorateCode => $cities) {
            $governorate = Governorate::query()->where('code', $governorateCode)->first();

            if ($governorate === null) {
                throw new RuntimeException(
                    "GovernorateSeeder must run before CitySeeder: [{$governorateCode}] is missing."
                );
            }

            foreach ($cities as $index => $city) {
                $model = City::query()->firstOrNew(['governorate_id' => $governorate->id, 'code' => $city['code']]);

                $model->fill([
                    'governorate_id' => $governorate->getKey(),
                    'code' => $city['code'],
                    'postal_code' => null,
                    // Absent coordinates stay null rather than becoming 0,0, which
                    // would place the city in the Gulf of Guinea on a map.
                    'latitude' => $city['lat'] ?? null,
                    'longitude' => $city['lng'] ?? null,
                    'is_active' => $model->exists ? $model->is_active : true,
                    'sort_order' => $index * self::SORT_STEP,
                ]);

                $model->fill(['translations' => $this->translations($city, $locales)]);

                $model->save();
            }
        }
    }

    /**
     * @param  array{en: string, ar: string, lat?: float, lng?: float}  $city
     * @param  list<string>  $locales
     * @return array<string, array{name: string}>
     */
    private function translations(array $city, array $locales): array
    {
        $translations = [];

        foreach ($locales as $locale) {
            if (isset($city[$locale])) {
                $translations[$locale] = ['name' => $city[$locale]];
            }
        }

        return $translations;
    }
}
