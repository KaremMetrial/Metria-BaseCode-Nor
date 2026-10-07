<?php

namespace Tests\Feature\Locations;

use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\User;
use App\Support\Access\PermissionRegistry;
use App\Support\Access\RoleRegistry;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Administrative location management (/api/v1/admin/locations/*).
 *
 * The permission tests matter more than the happy paths. `locations.manage` is a
 * genuinely dangerous ability -- changing the geography changes what every stored
 * address means -- so the boundary is tested from each side: unauthenticated,
 * wrong actor, right actor with the wrong role, and the super-admin bypass.
 */
class AdminLocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    // ---------------------------------------------------------------------
    // Access control
    // ---------------------------------------------------------------------

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/admin/locations/countries')
            ->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_a_client_token_cannot_reach_the_admin_surface(): void
    {
        Sanctum::actingAs(User::factory()->client()->create());

        $this->getJson('/api/v1/admin/locations/countries')
            ->assertStatus(403)
            ->assertJson(['code' => 'FORBIDDEN']);
    }

    public function test_an_administrator_without_the_permission_is_forbidden(): void
    {
        // A valid admin token is not enough: `actor:admin` is the actor boundary,
        // the permission is the capability boundary.
        Sanctum::actingAs($this->adminWithRole(null));

        $this->getJson('/api/v1/admin/locations/countries')
            ->assertStatus(403)
            ->assertJson(['code' => 'FORBIDDEN']);
    }

    public function test_a_support_agent_cannot_manage_locations(): void
    {
        Sanctum::actingAs($this->adminWithRole(RoleRegistry::SUPPORT_AGENT));

        $this->getJson('/api/v1/admin/locations/countries')->assertStatus(403);
        $this->postJson('/api/v1/admin/locations/countries', [])->assertStatus(403);
    }

    public function test_a_super_admin_can_read_and_manage_locations(): void
    {
        Sanctum::actingAs($this->adminWithRole(RoleRegistry::SUPER_ADMIN));

        $this->getJson('/api/v1/admin/locations/countries')->assertOk();
    }

    public function test_the_permission_and_role_aliases_are_registered(): void
    {
        // Spatie does not register these; without them every admin route fails at
        // resolution with "Target class [permission] does not exist".
        $aliases = app('router')->getMiddleware();

        $this->assertArrayHasKey('permission', $aliases);
        $this->assertArrayHasKey('role', $aliases);
    }

    // ---------------------------------------------------------------------
    // Countries
    // ---------------------------------------------------------------------

    public function test_listing_includes_inactive_rows_and_every_translation(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $country = Country::factory()
            ->withIso2('EG')
            ->withArabicName('مصر')
            ->inactive()
            ->create(['calling_code' => '+20']);

        $response = $this->getJson('/api/v1/admin/locations/countries');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $country->getKey())
            ->assertJsonPath('data.0.is_active', false)
            ->assertJsonPath('data.0.translations.en.name', $country->fresh()->translate('en')->name)
            ->assertJsonPath('data.0.translations.ar.name', 'مصر');
    }

    public function test_a_country_is_created_with_its_translations(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $response = $this->postJson('/api/v1/admin/locations/countries', [
            'iso2' => 'eg',
            'calling_code' => '+20',
            'currency_code' => 'egp',
            'is_active' => true,
            'translations' => [
                'en' => ['name' => 'Egypt', 'nationality' => 'Egyptian'],
                'ar' => ['name' => 'مصر', 'nationality' => 'مصري'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.iso2', 'EG')
            ->assertJsonPath('data.currency_code', 'EGP')
            ->assertJsonPath('data.translations.en.name', 'Egypt')
            ->assertJsonPath('data.translations.ar.name', 'مصر');

        // Case normalisation is enforced server-side: without it "eg" and "EG"
        // are two rows the unique index happily accepts.
        $this->assertDatabaseHas('countries', ['iso2' => 'EG', 'currency_code' => 'EGP']);
        $this->assertDatabaseCount('country_translations', 2);
    }

    public function test_a_name_in_the_default_locale_is_required(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $response = $this->postJson('/api/v1/admin/locations/countries', [
            'iso2' => 'EG',
            'calling_code' => '+20',
            'translations' => ['ar' => ['name' => 'مصر']],
        ]);

        $response->assertStatus(422)
            ->assertJson(['code' => 'VALIDATION_FAILED'])
            ->assertJsonValidationErrors(['translations.en.name']);

        $this->assertDatabaseCount('countries', 0);
    }

    public function test_an_unsupported_locale_is_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/v1/admin/locations/countries', [
            'iso2' => 'FR',
            'calling_code' => '+33',
            'translations' => [
                'en' => ['name' => 'France'],
                'fr' => ['name' => 'France'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['translations']);
    }

    public function test_a_supplied_locale_block_must_carry_its_required_columns(): void
    {
        Sanctum::actingAs($this->superAdmin());

        // An empty Arabic block would create a translation row with no name and
        // fail at the database. `required_array_keys` turns that into a 422.
        $this->postJson('/api/v1/admin/locations/countries', [
            'iso2' => 'EG',
            'calling_code' => '+20',
            'translations' => [
                'en' => ['name' => 'Egypt'],
                'ar' => [],
            ],
        ])->assertStatus(422);
    }

    public function test_a_duplicate_iso2_is_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin());

        Country::factory()->withIso2('EG')->create(['calling_code' => '+20']);

        $this->postJson('/api/v1/admin/locations/countries', [
            'iso2' => 'EG',
            'calling_code' => '+20',
            'translations' => ['en' => ['name' => 'Egypt']],
        ])->assertStatus(422)->assertJsonValidationErrors(['iso2']);
    }

    public function test_a_country_can_be_partially_updated_without_re_sending_its_identity(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $country = Country::factory()
            ->withIso2('EG')
            ->withArabicName('مصر')
            ->create(['calling_code' => '+20']);

        $englishName = $country->fresh()->translate('en')->name;

        $response = $this->patchJson(
            '/api/v1/admin/locations/countries/'.$country->getKey(),
            ['translations' => ['ar' => ['name' => 'مصر الجديدة']]],
        );

        $response->assertOk()
            ->assertJsonPath('data.translations.ar.name', 'مصر الجديدة')
            ->assertJsonPath('data.translations.en.name', $englishName);

        // The untouched locale must survive a PATCH that never mentioned it.
        $this->assertSame($englishName, $country->fresh()->translate('en')->name);
    }

    public function test_patching_a_country_with_its_own_iso2_is_not_a_duplicate(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $country = Country::factory()->withIso2('EG')->create(['calling_code' => '+20']);

        // The unique rule has to ignore the record being updated. It can only do
        // that if the route parameter is bound to the model, not to an id string.
        $this->patchJson(
            '/api/v1/admin/locations/countries/'.$country->getKey(),
            ['iso2' => 'EG', 'calling_code' => '+20'],
        )->assertOk();
    }

    public function test_a_country_referenced_by_a_user_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $country = Country::factory()->withIso2('EG')->create(['calling_code' => '+20']);

        User::factory()->client()->create(['phone_country_id' => $country->getKey()]);

        $this->deleteJson('/api/v1/admin/locations/countries/'.$country->getKey())
            ->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'RESOURCE_IN_USE',
            ]);

        $this->assertDatabaseHas('countries', ['id' => $country->getKey()]);
    }

    public function test_a_country_that_still_has_governorates_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $country = Country::factory()->withIso2('EG')->create(['calling_code' => '+20']);
        Governorate::factory()->forCountry($country)->create();

        $this->deleteJson('/api/v1/admin/locations/countries/'.$country->getKey())
            ->assertStatus(409)
            ->assertJson(['code' => 'RESOURCE_IN_USE']);
    }

    public function test_an_unreferenced_country_can_be_deleted_with_its_translations(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $country = Country::factory()->withIso2('NR')->create(['calling_code' => '+674']);

        $this->deleteJson('/api/v1/admin/locations/countries/'.$country->getKey())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('countries', ['id' => $country->getKey()]);

        // The translation rows go with it, by the cascade on the foreign key
        // rather than by an orphan cleanup job.
        $this->assertDatabaseCount('country_translations', 0);
    }

    // ---------------------------------------------------------------------
    // Governorates
    // ---------------------------------------------------------------------

    public function test_the_governorate_listing_requires_a_country_filter(): void
    {
        Sanctum::actingAs($this->superAdmin());

        // An unfiltered listing of every division in the database is a query
        // nobody wants and one that gets expensive the moment real data lands.
        $this->getJson('/api/v1/admin/locations/governorates')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['country_id']);
    }

    public function test_governorates_are_listed_for_one_country(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $egypt = Country::factory()->withIso2('EG')->create(['calling_code' => '+20']);
        $saudi = Country::factory()->withIso2('SA')->create(['calling_code' => '+966']);

        $cairo = Governorate::factory()->forCountry($egypt)->withCode('EG-C')->create();
        Governorate::factory()->forCountry($saudi)->withCode('SA-01')->create();

        $response = $this->getJson(
            '/api/v1/admin/locations/governorates?country_id='.$egypt->getKey()
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cairo->getKey())
            ->assertJsonPath('data.0.code', 'EG-C');
    }

    public function test_a_governorate_code_is_unique_within_a_country_but_not_across_countries(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $egypt = Country::factory()->withIso2('EG')->create(['calling_code' => '+20']);
        $saudi = Country::factory()->withIso2('SA')->create(['calling_code' => '+966']);

        $payload = fn (int $countryId): array => [
            'country_id' => $countryId,
            'code' => '01',
            'translations' => ['en' => ['name' => 'First level']],
        ];

        $this->postJson('/api/v1/admin/locations/governorates', $payload($egypt->getKey()))
            ->assertStatus(201);

        // Same code, different country: legitimate, because the code is only
        // meaningful inside its country.
        $this->postJson('/api/v1/admin/locations/governorates', $payload($saudi->getKey()))
            ->assertStatus(201);

        $this->postJson('/api/v1/admin/locations/governorates', $payload($egypt->getKey()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_a_governorate_type_must_be_a_known_enum_value(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $egypt = Country::factory()->withIso2('EG')->create(['calling_code' => '+20']);

        $this->postJson('/api/v1/admin/locations/governorates', [
            'country_id' => $egypt->getKey(),
            'type' => 'duchy',
            'translations' => ['en' => ['name' => 'Nowhere']],
        ])->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_governorates_may_be_created_without_a_code(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $egypt = Country::factory()->withIso2('EG')->create(['calling_code' => '+20']);

        // `code` is optional and MySQL treats NULLs as distinct, so several
        // code-less divisions must be able to coexist.
        foreach (['One', 'Two'] as $name) {
            $this->postJson('/api/v1/admin/locations/governorates', [
                'country_id' => $egypt->getKey(),
                'translations' => ['en' => ['name' => $name]],
            ])->assertStatus(201);
        }

        $this->assertDatabaseCount('governorates', 2);
    }

    public function test_a_governorate_can_be_updated_without_re_sending_its_country(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $egypt = Country::factory()->withIso2('EG')->create(['calling_code' => '+20']);
        $governorate = Governorate::factory()
            ->forCountry($egypt)
            ->withCode('EG-C')
            ->create();

        // Re-sending the same code with no `country_id` must not be read as a
        // duplicate, and must not scope the uniqueness check to a null country.
        $this->patchJson(
            '/api/v1/admin/locations/governorates/'.$governorate->getKey(),
            ['code' => 'EG-C', 'translations' => ['en' => ['name' => 'Cairo']]],
        )->assertOk();
    }

    public function test_a_governorate_with_cities_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $governorate = Governorate::factory()->withCode('EG-C')->create();
        City::factory()->forGovernorate($governorate)->create();

        $this->deleteJson('/api/v1/admin/locations/governorates/'.$governorate->getKey())
            ->assertStatus(409)
            ->assertJson(['code' => 'RESOURCE_IN_USE']);
    }

    public function test_an_empty_governorate_can_be_deleted(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $governorate = Governorate::factory()->withCode('EG-C')->create();

        $this->deleteJson('/api/v1/admin/locations/governorates/'.$governorate->getKey())
            ->assertOk();

        $this->assertDatabaseMissing('governorates', ['id' => $governorate->getKey()]);
    }

    // ---------------------------------------------------------------------
    // Cities
    // ---------------------------------------------------------------------

    public function test_the_city_listing_requires_a_governorate_filter(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->getJson('/api/v1/admin/locations/cities')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['governorate_id']);
    }

    public function test_a_city_is_created_with_coordinates_and_translations(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $governorate = Governorate::factory()->withCode('EG-C')->create();

        $response = $this->postJson('/api/v1/admin/locations/cities', [
            'governorate_id' => $governorate->getKey(),
            'code' => 'cai',
            'postal_code' => '11511',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'translations' => [
                'en' => ['name' => 'Cairo'],
                'ar' => ['name' => 'القاهرة'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'CAI')
            ->assertJsonPath('data.translations.ar.name', 'القاهرة')
            ->assertJsonPath('data.governorate_id', $governorate->getKey());

        $this->assertSame(30.0444, $response->json('data.latitude'));
    }

    public function test_city_coordinates_are_range_checked(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $governorate = Governorate::factory()->create();

        $this->postJson('/api/v1/admin/locations/cities', [
            'governorate_id' => $governorate->getKey(),
            'latitude' => 91,
            'translations' => ['en' => ['name' => 'Off the map']],
        ])->assertStatus(422)->assertJsonValidationErrors(['latitude']);

        $this->postJson('/api/v1/admin/locations/cities', [
            'governorate_id' => $governorate->getKey(),
            'longitude' => -180.5,
            'translations' => ['en' => ['name' => 'Off the map']],
        ])->assertStatus(422)->assertJsonValidationErrors(['longitude']);
    }

    public function test_a_city_needs_an_existing_governorate(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/v1/admin/locations/cities', [
            'governorate_id' => 999_999,
            'translations' => ['en' => ['name' => 'Nowhere']],
        ])->assertStatus(422)->assertJsonValidationErrors(['governorate_id']);
    }

    public function test_a_city_can_be_deleted(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $city = City::factory()->create();

        $this->deleteJson('/api/v1/admin/locations/cities/'.$city->getKey())->assertOk();

        $this->assertDatabaseMissing('cities', ['id' => $city->getKey()]);
    }

    public function test_a_missing_location_is_a_clean_404(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->getJson('/api/v1/admin/locations/countries/999999')
            ->assertStatus(404)
            ->assertJson(['code' => 'NOT_FOUND']);
    }

    public function test_the_registry_permissions_used_by_these_routes_exist(): void
    {
        // Guards against a typo'd constant silently making a route unusable for
        // everyone but the super admin.
        foreach ([PermissionRegistry::LOCATIONS_READ, PermissionRegistry::LOCATIONS_MANAGE] as $permission) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function superAdmin(): User
    {
        return $this->adminWithRole(RoleRegistry::SUPER_ADMIN);
    }

    private function adminWithRole(?string $role): User
    {
        $user = User::factory()->admin()->withoutPhone()->create();

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user->fresh();
    }
}
