<?php

use App\Http\Controllers\Shared\MediaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Versioned from day one so a v2 can be introduced without breaking clients.
| Each actor gets its own route file. Actor-exclusive controllers, requests,
| resources and actions live under that actor's namespace. Reused behavior,
| such as client and vendor payments, lives under Shared.
|
| Actor separation is enforced by the `actor:<type>` middleware inside each
| file. The URL prefix is only routing and must never be treated as security.
|
| Adding a new actor (courier, employee, support, provider) means adding one
| enum case and registering its route file here.
|
*/

Route::prefix('v1')
    ->as('api.v1.')
    ->group(function (): void {
        // Provider callbacks authenticate by signature, not by session, so they
        // sit outside the actor groups.
        require base_path('routes/shared/webhooks.php');

        // Public reference data: geography is needed before authentication
        // exists (country picker, calling code for the phone/OTP flow).
        require base_path('routes/shared/locations.php');
        require base_path('routes/shared/categories.php');
        require base_path('routes/shared/config.php');
        require base_path('routes/shared/links.php');
        require base_path('routes/shared/content.php');
        Route::get('media/{media}', [MediaController::class, 'public'])->whereUuid('media')->middleware('throttle:api')->name('media.public');
        Route::get('media/{media}/avatar', [MediaController::class, 'avatar'])->whereUuid('media')->middleware(['signed', 'throttle:api'])->name('media.avatar');

        require base_path('routes/admin/api.php');
        require base_path('routes/client/api.php');
        require base_path('routes/vendor/api.php');
    });
