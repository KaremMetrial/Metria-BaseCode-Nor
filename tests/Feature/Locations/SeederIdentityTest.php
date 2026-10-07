<?php

namespace Tests\Feature\Locations;

use App\Models\City;
use App\Models\Country;
use Database\Seeders\CitySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_translated_name_changes_do_not_duplicate_seed_identity_or_reenable_country(): void
    {
        $this->seed([CountrySeeder::class, GovernorateSeeder::class, CitySeeder::class]);
        $city = City::query()->whereNotNull('code')->firstOrFail();
        $id = $city->id;
        $city->translate('en')->name = 'Edited name';
        $city->save();
        $before = City::count();
        $country = Country::query()->where('iso2', 'EG')->firstOrFail();
        $country->is_active = false;
        $country->save();
        $this->seed([CountrySeeder::class, CitySeeder::class]);
        $this->assertSame($before, City::count());
        $this->assertSame($id, City::where('code', $city->code)->firstOrFail()->id);
        $this->assertFalse($country->fresh()->is_active);
    }
}
