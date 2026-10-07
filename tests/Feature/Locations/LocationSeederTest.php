<?php

namespace Tests\Feature\Locations;

use App\Contracts\Phone\PhoneNumberServiceInterface;
use App\Enums\GovernorateType;
use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use Database\Seeders\CitySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The reference-data seeders.
 *
 * Seeding runs on every deploy, so "does it work" is the wrong question -- the
 * questions are "is it complete" and "is it idempotent". A seeder that duplicates
 * a row or silently drops a translation is only discovered in production, and by
 * then it has been discovered by a user.
 *
 * The expensive country seeding is deliberately concentrated in one test: the
 * whole set is generated, and generating it repeatedly would dominate the suite's
 * runtime for no extra coverage.
 */
class LocationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_seeding_is_complete_idempotent_and_matches_the_numbering_plan(): void
    {
        $this->seed(CountrySeeder::class);

        $phone = app(PhoneNumberServiceInterface::class);
        $regions = $phone->supportedRegions();

        $this->assertSame(count($regions), Country::count());

        // Snapshot before the second run. Comparing the two states is what proves
        // idempotency; asserting only the count would pass even if rows were
        // deleted and re-created with new ids that users reference.
        $countriesBefore = Country::query()->orderBy('id')->pluck('iso2')->all();
        $translationsBefore = DB::table('country_translations')->count();
        $translationTimestamp = DB::table('country_translations')->orderBy('id')->value('updated_at');

        $this->seed(CountrySeeder::class);

        $this->assertSame($countriesBefore, Country::query()->orderBy('id')->pluck('iso2')->all());
        $this->assertSame($translationsBefore, DB::table('country_translations')->count());

        // An unchanged translation must not be rewritten. Churning `updated_at`
        // on every deploy makes "when did this name last change?" unanswerable.
        $this->assertSame(
            $translationTimestamp,
            DB::table('country_translations')->orderBy('id')->value('updated_at'),
        );

        $countries = Country::query()->get()->keyBy('iso2');

        /** @var array<string, array{currency: string, timezone: string}> $markets */
        $markets = require database_path('data/countries.php');

        // Only the declared markets are live. Everything else is seeded inactive
        // as the list an administrator picks from to open a new market.
        $this->assertEqualsCanonicalizing(
            array_keys($markets),
            Country::query()->where('is_active', true)->pluck('iso2')->all(),
        );

        $defaultLocale = (string) config('languages.default');

        foreach ($regions as $region) {
            $country = $countries[$region] ?? null;

            $this->assertNotNull($country, "Region {$region} was not seeded");

            // The calling code is derived from the same authority the phone
            // service validates against, so the two can never disagree.
            $this->assertSame($phone->callingCode($region), $country->calling_code, $region);

            if ($country->phone_example !== null) {
                $this->assertTrue(
                    $phone->isValid($country->phone_example),
                    "{$region}: seeded phone_example [{$country->phone_example}] is not a valid number",
                );
            }
        }

        // Every country must render in the default locale, or it shows up as a
        // blank option in a picker.
        $this->assertSame(
            0,
            Country::query()
                ->whereDoesntHave('translations', fn ($query) => $query->where('locale', $defaultLocale))
                ->count(),
        );

        $egypt = $countries['EG'];

        $this->assertTrue($egypt->is_active);
        $this->assertSame('EGP', $egypt->currency_code);
        $this->assertSame('Africa/Cairo', $egypt->timezone_default);
        $this->assertContains($egypt->timezone_default, timezone_identifiers_list());

        // Localized names come from ICU data, not from a hand-typed table.
        $this->assertSame('Egypt', $egypt->translate('en')->name);
        $this->assertSame('مصر', $egypt->translate('ar')->name);
    }

    public function test_the_location_hierarchy_is_seeded_from_the_declared_data(): void
    {
        $this->seed([CountrySeeder::class, GovernorateSeeder::class, CitySeeder::class]);

        /** @var array<string, list<array{code: string, en: string, ar: string}>> $governorateData */
        $governorateData = require database_path('data/governorates.php');

        /** @var array<string, list<array{en: string, ar: string}>> $cityData */
        $cityData = require database_path('data/cities.php');

        $expectedGovernorates = array_sum(array_map('count', $governorateData));
        $expectedCities = array_sum(array_map('count', $cityData));

        $this->assertSame($expectedGovernorates, Governorate::count());
        $this->assertSame($expectedCities, City::count());

        $egypt = Country::query()->iso2('EG')->firstOrFail();

        $this->assertSame(
            count($governorateData['EG']),
            Governorate::query()->forCountry($egypt->getKey())->count(),
        );

        // The local word for a division is derived from its country, so the same
        // table describes governorates, regions and emirates truthfully.
        foreach (['EG' => GovernorateType::GOVERNORATE, 'SA' => GovernorateType::REGION, 'AE' => GovernorateType::EMIRATE] as $iso2 => $type) {
            $country = Country::query()->iso2($iso2)->firstOrFail();

            $wronglyTyped = Governorate::query()
                ->forCountry($country->getKey())
                ->where('type', '!=', $type->value)
                ->count();

            $this->assertSame(0, $wronglyTyped, "Divisions of {$iso2} are not typed {$type->value}");
        }

        $cairo = Governorate::query()->where('code', 'EG-C')->with('translations')->firstOrFail();

        $this->assertSame('Cairo', $cairo->translate('en')->name);
        $this->assertSame('القاهرة', $cairo->translate('ar')->name);
        $this->assertSame($egypt->getKey(), $cairo->country_id);

        // Cities carry their real parent, and coordinates are optional rather
        // than defaulted to 0,0.
        $newCairo = City::query()
            ->where('governorate_id', $cairo->getKey())
            ->whereHas('translations', fn ($query) => $query->where('name', 'New Cairo'))
            ->firstOrFail();

        $this->assertSame(30.03, $newCairo->latitude);

        // The two cheaper seeders are re-run to prove they are idempotent too.
        // They are matched on (country, code) and (governorate, localized name),
        // because cities are deliberately seeded without a code.
        $snapshot = [
            Governorate::count(),
            City::count(),
            DB::table('governorate_translations')->count(),
            DB::table('city_translations')->count(),
        ];

        $this->seed([GovernorateSeeder::class, CitySeeder::class]);

        $this->assertSame($snapshot, [
            Governorate::count(),
            City::count(),
            DB::table('governorate_translations')->count(),
            DB::table('city_translations')->count(),
        ]);
    }

    public function test_the_governorate_seeder_refuses_to_run_without_its_countries(): void
    {
        // A silent skip would look like a data problem for a long time before
        // anyone traced it back to the order seeders ran in.
        $this->expectExceptionMessage('CountrySeeder must run before GovernorateSeeder');

        $this->seed(GovernorateSeeder::class);
    }
}
