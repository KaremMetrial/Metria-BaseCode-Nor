<?php

use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\GovernorateController;
use App\Support\Access\PermissionRegistry;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Locations -- administration  (/api/v1/admin/locations/*)
|--------------------------------------------------------------------------
|
| Already inside the `auth:sanctum`, `actor:admin`, `active`, `throttle:api`
| group (required from routes/admin.php), so only the fine-grained permission
| split is declared here.
|
| Read and manage are separate permissions because they are separate jobs: a
| support agent can legitimately look at the location list to answer "do we
| deliver to X?" without being able to restructure the geography, which changes
| what every existing address means.
|
| Every write request is authorized a second time inside its FormRequest, so a
| route added later without the middleware still refuses the write.
|
*/

Route::prefix('locations')
    ->as('locations.')
    ->whereNumber('country')
    ->whereNumber('governorate')
    ->whereNumber('city')
    ->group(function (): void {
        Route::middleware('permission:'.PermissionRegistry::LOCATIONS_READ)
            ->group(function (): void {
                Route::get('countries', [CountryController::class, 'index'])->name('countries.index');
                Route::get('countries/{country}', [CountryController::class, 'show'])->name('countries.show');
                Route::get('governorates', [GovernorateController::class, 'index'])->name('governorates.index');
                Route::get('governorates/{governorate}', [GovernorateController::class, 'show'])->name('governorates.show');
                Route::get('cities', [CityController::class, 'index'])->name('cities.index');
                Route::get('cities/{city}', [CityController::class, 'show'])->name('cities.show');
            });

        Route::middleware('permission:'.PermissionRegistry::LOCATIONS_MANAGE)
            ->group(function (): void {
                Route::post('countries', [CountryController::class, 'store'])->name('countries.store');
                Route::patch('countries/{country}', [CountryController::class, 'update'])->name('countries.update');
                Route::delete('countries/{country}', [CountryController::class, 'destroy'])->name('countries.destroy');

                Route::post('governorates', [GovernorateController::class, 'store'])->name('governorates.store');
                Route::patch('governorates/{governorate}', [GovernorateController::class, 'update'])->name('governorates.update');
                Route::delete('governorates/{governorate}', [GovernorateController::class, 'destroy'])->name('governorates.destroy');

                Route::post('cities', [CityController::class, 'store'])->name('cities.store');
                Route::patch('cities/{city}', [CityController::class, 'update'])->name('cities.update');
                Route::delete('cities/{city}', [CityController::class, 'destroy'])->name('cities.destroy');
            });
    });
