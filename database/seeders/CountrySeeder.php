<?php

namespace Database\Seeders;

use App\Contracts\Phone\PhoneNumberServiceInterface;
use App\Models\Country;
use Giggsey\Locale\Locale;
use Illuminate\Database\Seeder;

/**
 * Seeds every country that has a numbering plan, and switches on the markets we
 * operate in.
 *
 * Why generate instead of shipping a hand-written list of 245 rows:
 *
 *  - The country list is not ours to maintain. `PhoneNumberServiceInterface`
 *    already answers "which regions exist and what is their calling code" from
 *    libphonenumber, and `users.phone_country_id` is a FK to this table, so the
 *    two must not be able to disagree. Deriving one from the other makes that
 *    disagreement impossible.
 *  - Localized names come from `giggsey/locale` (ICU data, already installed as
 *    a libphonenumber dependency), so English *and* Arabic names exist for all
 *    245 regions without a single hand-typed translation -- and adding a third
 *    locale later costs nothing.
 *  - Only the currencies and timezones of the operating markets are declared, in
 *    database/data/countries.php, because no numbering plan knows them.
 *
 * Idempotent: rows are matched on `iso2` (the unique key that identifies a
 * country) and updated in place, so re-seeding after a libphonenumber upgrade
 * refreshes names and examples without duplicating rows or touching ids that
 * users reference.
 *
 * Deactivated countries are seeded anyway, deliberately. They are the list an
 * administrator picks from to open a new market, and the alternative -- 404s for
 * every country the product might grow into -- makes expansion a deploy instead
 * of a toggle. `is_active` is what decides whether we operate somewhere, exactly
 * as the spec requires; the row existing is not a promise to serve it.
 */
class CountrySeeder extends Seeder
{
    /**
     * Sort position for countries that are not operating markets: after every
     * listed market, which is where an administrator expects to find them.
     */
    private const UNLISTED_SORT_ORDER = 1000;

    public function run(): void
    {
        $phone = app(PhoneNumberServiceInterface::class);

        /** @var array<string, array{currency: string, timezone: string}> $markets */
        $markets = require database_path('data/countries.php');

        $marketOrder = array_flip(array_keys($markets));

        /** @var list<string> $locales */
        $locales = array_keys(config('languages.supported', []));

        foreach ($phone->supportedRegions() as $iso2) {
            $callingCode = $phone->callingCode($iso2);

            // `calling_code` is NOT NULL and is the display half of every stored
            // E.164 number. A region the numbering authority cannot give a code
            // for is one we could never create a phone identity in, so it is
            // skipped rather than seeded half-formed.
            if ($callingCode === null) {
                continue;
            }

            $country = Country::query()->firstOrNew(['iso2' => $iso2]);

            $country->fill([
                'iso2' => $iso2,
                'calling_code' => $callingCode,
                'currency_code' => $markets[$iso2]['currency'] ?? null,
                'timezone_default' => $markets[$iso2]['timezone'] ?? null,
                'phone_example' => $phone->exampleNumber($iso2),
                'is_active' => $country->exists ? $country->is_active : isset($markets[$iso2]),
                'sort_order' => isset($marketOrder[$iso2])
                    ? $marketOrder[$iso2] + 1
                    : self::UNLISTED_SORT_ORDER,
            ]);

            $country->fill(['translations' => $this->translations($iso2, $locales)]);

            $country->save();
        }
    }

    /**
     * Localized names for one region, keyed by locale.
     *
     * @param  list<string>  $locales
     * @return array<string, array{name: string}>
     */
    private function translations(string $iso2, array $locales): array
    {
        $translations = [];

        foreach ($locales as $locale) {
            $name = Locale::getDisplayRegion("en-{$iso2}", $locale);

            if ($name === '') {
                continue;
            }

            $translations[$locale] = ['name' => $name];
        }

        $default = (string) config('languages.default', 'en');

        // A country with no name in the default locale renders as a blank option
        // in every picker, which looks like a bug to a user. The ISO code is a
        // truthful, if terse, last resort -- and in practice ICU always has a
        // name for a region that owns a numbering plan.
        if (! isset($translations[$default])) {
            $translations[$default] = ['name' => $iso2];
        }

        return $translations;
    }
}
