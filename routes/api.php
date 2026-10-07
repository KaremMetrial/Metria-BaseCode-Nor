<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Versioned from day one so a v2 can be introduced without breaking clients.
| Each actor gets its own route file, but the business logic behind them is
| shared: an admin wallet adjustment and a vendor wallet adjustment call the
| same Action.
|
| Actor separation is enforced by the `actor:<type>` middleware inside each
| file. The URL prefix is only routing and must never be treated as security.
|
| Adding a new actor (courier, employee, support, provider) means adding one
| enum case and one file here.
|
*/

Route::prefix('v1')
    ->as('api.v1.')
    ->group(function (): void {
        // Provider callbacks authenticate by signature, not by session, so they
        // sit outside the actor groups.
        require base_path('routes/webhooks.php');

        // Public reference data: geography is needed before authentication
        // exists (country picker, calling code for the phone/OTP flow).
        require base_path('routes/locations.php');
        Route::get('categories',[\App\Http\Controllers\Shared\CategoryController::class,'index'])->middleware('throttle:api')->name('categories.index');
        Route::get('categories/{category}',[\App\Http\Controllers\Shared\CategoryController::class,'show'])->whereNumber('category')->middleware('throttle:api')->name('categories.show');

        require base_path('routes/admin.php');
        require base_path('routes/client.php');
        require base_path('routes/vendor.php');
    });
