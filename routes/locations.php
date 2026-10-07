<?php

use App\Http\Controllers\Shared\CityController;
use App\Http\Controllers\Shared\CountryController;
use App\Http\Controllers\Shared\GovernorateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Locations -- public reference data  (/api/v1/locations/*)
|--------------------------------------------------------------------------
|
| Geography is the one resource that cannot sit behind authentication: a user
| has to pick a country before they have an account, and the phone/OTP flow
| needs the country's calling code to render its own input. So these reads are
| public, which is exactly why they are also the most tightly constrained:
|
|   - only `is_active` rows are reachable (see the Shared controllers);
|   - every id is asserted to be numeric, so a route parameter can never reach
|     an `int` type hint as a string and become a 500;
|   - the whole group is throttled by IP, because it is anonymous and cheap to
|     hammer.
|
| Writes are admin-only and live in routes/admin.php.
|
*/

Route::prefix('locations')
    ->as('locations.')
    ->middleware('throttle:api')
    ->whereNumber(['country', 'governorate', 'city'])
    ->group(function (): void {
        // Step 1: the countries we operate in.
        Route::get('countries', [CountryController::class, 'index'])->name('countries.index');
        Route::get('countries/{country}', [CountryController::class, 'show'])->name('countries.show');

        // Step 2: the divisions of a country.
        Route::get('countries/{country}/governorates', [CountryController::class, 'governorates'])
            ->name('countries.governorates');
        Route::get('governorates/{governorate}', [GovernorateController::class, 'show'])
            ->name('governorates.show');

        // Step 3: the cities of a division.
        Route::get('governorates/{governorate}/cities', [GovernorateController::class, 'cities'])
            ->name('governorates.cities');
        Route::get('cities/{city}', [CityController::class, 'show'])->name('cities.show');
    });
