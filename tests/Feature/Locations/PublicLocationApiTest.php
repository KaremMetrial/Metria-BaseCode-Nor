<?php

namespace Tests\Feature\Locations;

use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public geography surface (/api/v1/locations/*).
 *
 * Two themes run through these tests, and both are security-relevant rather than
 * cosmetic:
 *
 *  - *Visibility.* Deactivated reference data must disappear from every public
 *    endpoint, including indirectly: deactivating a country has to take its
 *    governorates and cities offline too, or an id learned earlier keeps working.
 *  - *Disclosure.* The public payload is a fixed contract. Administrative fields
 *    (`is_active`, `sort_order`, timestamps) and the other locales' translations
 *    must not ride along, which is asserted structurally rather than by spot
 *    checking a field.
 */
class PublicLocationApiTest extends TestCase
{
    use RefreshDatabase;

    private Country $egypt;

    private Governorate $cairo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->egypt = Country::factory()
            ->withIso2('EG')
            ->withArabicName('مصر')
            ->create([
                'calling_code' => '+20',
                'currency_code' => 'EGP',
                'is_active' => true,
                'sort_order' => 1,
            ]);

        $this->cairo = Governorate::factory()
            ->forCountry($this->egypt)
            ->withCode('EG-C')
            ->withArabicName('القاهرة')
            ->create(['sort_order' => 1]);
    }

    public function test_the_listing_uses_the_standard_success_envelope(): void
    {
        $response = $this->getJson('/api/v1/locations/countries');

        $response->assertOk()->assertJson([
            'success' => true,
            'message' => null,
            'data' => [
                [
                    'id' => $this->egypt->getKey(),
                    'iso2' => 'EG',
                    'name' => 'Egypt',
                    'calling_code' => '+20',
                ],
            ],
        ]);
    }

    public function test_only_active_countries_are_listed(): void
    {
        Country::factory()->withIso2('IQ')->inactive()->create();

        $this->getJson('/api/v1/locations/countries')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_countries_are_ordered_by_sort_order(): void
    {
        $second = Country::factory()->withIso2('AE')->create(['sort_order' => 2]);
        $third = Country::factory()->withIso2('SA')->create(['sort_order' => 3]);

        $response = $this->getJson('/api/v1/locations/countries');

        $this->assertSame(
            [$this->egypt->getKey(), $second->getKey(), $third->getKey()],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_country_names_follow_the_request_locale(): void
    {
        $this->getJson('/api/v1/locations/countries?locale=ar')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'مصر');

        $this->getJson('/api/v1/locations/countries?locale=en')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Egypt');
    }

    public function test_a_missing_translation_falls_back_to_the_default_locale(): void
    {
        // A country with only an English row still has to render in Arabic, or a
        // partially translated dataset produces blank options.
        $englishOnly = Country::factory()->withIso2('KE')->create();
        $englishName = $englishOnly->fresh()->translate('en')->name;

        $response = $this->getJson('/api/v1/locations/countries?locale=ar');
        $data = collect($response->json('data'))->keyBy('id');

        $this->assertSame($englishName, $data[$englishOnly->getKey()]['name']);
    }

    public function test_the_public_country_payload_exposes_exactly_the_agreed_fields(): void
    {
        $response = $this->getJson('/api/v1/locations/countries');

        $response->assertOk();

        // Pinned deliberately: adding a column to `countries` must not add it to
        // a public response by accident.
        $this->assertSame(
            ['id', 'iso2', 'iso3', 'name', 'nationality', 'calling_code', 'currency_code', 'phone_example'],
            array_keys($response->json('data.0')),
        );
    }

    public function test_the_public_payload_returns_only_the_active_locale(): void
    {
        $response = $this->getJson('/api/v1/locations/countries');

        $response->assertOk();

        // The Arabic name exists in the database; it must not appear in an
        // English response.
        $this->assertArrayNotHasKey('translations', $response->json('data.0'));
        $this->assertStringNotContainsString('مصر', (string) $response->getContent());
    }

    public function test_an_inactive_country_is_not_found(): void
    {
        $inactive = Country::factory()->withIso2('IQ')->inactive()->create();

        $this->getJson('/api/v1/locations/countries/'.$inactive->getKey())
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    public function test_a_non_numeric_country_id_never_reaches_the_controller(): void
    {
        // The route constrains the parameter to digits, so this is a routing 404
        // rather than an `int` type error in the controller -- which would be a
        // 500 that any anonymous caller could trigger.
        $this->getJson('/api/v1/locations/countries/not-a-number')->assertStatus(404);
    }

    public function test_a_countrys_governorates_are_listed(): void
    {
        $governorates = $this->getJson(
            '/api/v1/locations/countries/'.$this->egypt->getKey().'/governorates'
        );

        $governorates->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->cairo->getKey())
            ->assertJsonPath('data.0.country_id', $this->egypt->getKey())
            ->assertJsonPath('data.0.type', 'governorate');
    }

    public function test_inactive_governorates_are_hidden(): void
    {
        Governorate::factory()->forCountry($this->egypt)->inactive()->create();

        $this->getJson('/api/v1/locations/countries/'.$this->egypt->getKey().'/governorates')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_governorates_of_an_inactive_country_are_not_found(): void
    {
        $inactive = Country::factory()->withIso2('IQ')->inactive()->create();
        Governorate::factory()->forCountry($inactive)->create();

        $this->getJson('/api/v1/locations/countries/'.$inactive->getKey().'/governorates')
            ->assertStatus(404);
    }

    public function test_governorate_and_city_payloads_are_localized_too(): void
    {
        $city = City::factory()
            ->forGovernorate($this->cairo)
            ->withArabicName('مدينة نصر')
            ->create();

        $this->getJson('/api/v1/locations/governorates/'.$this->cairo->getKey().'?locale=ar')
            ->assertOk()
            ->assertJsonPath('data.name', 'القاهرة');

        $this->getJson('/api/v1/locations/cities/'.$city->getKey().'?locale=ar')
            ->assertOk()
            ->assertJsonPath('data.name', 'مدينة نصر');
    }

    public function test_city_coordinates_serialise_as_numbers(): void
    {
        $city = City::factory()
            ->forGovernorate($this->cairo)
            ->atCoordinates(30.0444, 31.2357)
            ->create();

        $response = $this->getJson('/api/v1/locations/cities/'.$city->getKey());

        $response->assertOk();

        // Not strings: a `decimal:7` cast would emit "30.0444000", which a client
        // then has to coerce before it can place a marker.
        $this->assertSame(30.0444, $response->json('data.latitude'));
        $this->assertSame(31.2357, $response->json('data.longitude'));
    }

    public function test_deactivating_a_country_hides_its_cities(): void
    {
        $city = City::factory()->forGovernorate($this->cairo)->create();

        $this->egypt->forceFill(['is_active' => false])->save();

        // The whole chain matters: the city itself is still active and its
        // governorate is still active, so only a join up to the country catches it.
        $this->getJson('/api/v1/locations/cities/'.$city->getKey())->assertStatus(404);
        $this->getJson('/api/v1/locations/governorates/'.$this->cairo->getKey())->assertStatus(404);
        $this->getJson('/api/v1/locations/governorates/'.$this->cairo->getKey().'/cities')
            ->assertStatus(404);
    }

    public function test_deactivating_a_governorate_hides_its_cities(): void
    {
        $city = City::factory()->forGovernorate($this->cairo)->create();

        $this->cairo->forceFill(['is_active' => false])->save();

        $this->getJson('/api/v1/locations/cities/'.$city->getKey())->assertStatus(404);
    }

    public function test_the_whole_surface_is_reachable_without_authentication(): void
    {
        // Geographic reference data has to load *before* an account exists: the
        // country picker and the calling code both precede the phone/OTP flow.
        $city = City::factory()->forGovernorate($this->cairo)->create();

        $this->assertGuest();

        $this->getJson('/api/v1/locations/countries')->assertOk();
        $this->getJson('/api/v1/locations/countries/'.$this->egypt->getKey())->assertOk();
        $this->getJson('/api/v1/locations/governorates/'.$this->cairo->getKey())->assertOk();
        $this->getJson('/api/v1/locations/cities/'.$city->getKey())->assertOk();
    }
}
