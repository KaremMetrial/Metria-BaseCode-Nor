<?php

namespace Tests\Feature\Locations;

use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Rules\CityBelongsToGovernorate;
use App\Rules\GovernorateBelongsToCountry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Validator;
use Tests\TestCase;

/**
 * The relational half of location validation.
 *
 * `exists:countries,id` and `exists:governorates,id` each pass on their own even
 * when the pair is nonsense ("country = Egypt, governorate = Riyadh"). These
 * rules are the only thing standing between a client and a contradictory address,
 * so they get tested on their own rather than only through an endpoint.
 */
class LocationHierarchyRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_governorate_of_the_submitted_country_passes(): void
    {
        $country = Country::factory()->create();
        $governorate = Governorate::factory()->forCountry($country)->create();

        $this->assertFalse($this->validate(
            ['country_id' => $country->getKey(), 'governorate_id' => $governorate->getKey()],
            'governorate_id',
            new GovernorateBelongsToCountry,
        )->fails());
    }

    public function test_a_governorate_of_another_country_is_rejected(): void
    {
        $egypt = Country::factory()->withIso2('EG')->create();
        $saudi = Country::factory()->withIso2('SA')->create();
        $riyadh = Governorate::factory()->forCountry($saudi)->create();

        $validator = $this->validate(
            ['country_id' => $egypt->getKey(), 'governorate_id' => $riyadh->getKey()],
            'governorate_id',
            new GovernorateBelongsToCountry,
        );

        $this->assertTrue($validator->fails());

        // The message is localized, so assert against the lang key rather than a
        // literal -- otherwise translating it would break the test instead of the
        // behaviour it protects.
        $this->assertSame(
            __('locations.hierarchy.governorate_not_in_country'),
            $validator->errors()->first('governorate_id'),
        );
    }

    public function test_the_governorate_rule_defers_when_either_side_is_missing(): void
    {
        $governorate = Governorate::factory()->create();

        // Absence is `required`'s job. A second message on the same field would
        // replace the useful one ("you must choose a country") with a confusing
        // one ("this governorate does not belong to that country").
        $this->assertFalse($this->validate(
            ['governorate_id' => $governorate->getKey()],
            'governorate_id',
            new GovernorateBelongsToCountry,
        )->fails());

        $this->assertFalse($this->validate(
            ['country_id' => $governorate->country_id, 'governorate_id' => null],
            'governorate_id',
            new GovernorateBelongsToCountry,
        )->fails());

        $this->assertFalse($this->validate(
            ['country_id' => $governorate->country_id, 'governorate_id' => ''],
            'governorate_id',
            new GovernorateBelongsToCountry,
        )->fails());
    }

    public function test_a_city_of_the_submitted_governorate_passes(): void
    {
        $governorate = Governorate::factory()->create();
        $city = City::factory()->forGovernorate($governorate)->create();

        $this->assertFalse($this->validate(
            ['governorate_id' => $governorate->getKey(), 'city_id' => $city->getKey()],
            'city_id',
            new CityBelongsToGovernorate,
        )->fails());
    }

    public function test_a_city_of_another_governorate_is_rejected(): void
    {
        $cairo = Governorate::factory()->withCode('EG-C')->create();
        $giza = Governorate::factory()->create();
        $sixOctober = City::factory()->forGovernorate($giza)->create();

        $validator = $this->validate(
            ['governorate_id' => $cairo->getKey(), 'city_id' => $sixOctober->getKey()],
            'city_id',
            new CityBelongsToGovernorate,
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            __('locations.hierarchy.city_not_in_governorate'),
            $validator->errors()->first('city_id'),
        );
    }

    public function test_the_city_rule_defers_when_either_side_is_missing(): void
    {
        $city = City::factory()->create();

        $this->assertFalse($this->validate(
            ['city_id' => $city->getKey()],
            'city_id',
            new CityBelongsToGovernorate,
        )->fails());

        $this->assertFalse($this->validate(
            ['governorate_id' => $city->governorate_id, 'city_id' => null],
            'city_id',
            new CityBelongsToGovernorate,
        )->fails());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validate(array $data, string $field, object $rule): Validator
    {
        return \Illuminate\Support\Facades\Validator::make($data, [$field => [$rule]]);
    }
}
