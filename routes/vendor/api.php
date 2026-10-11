<?php

use App\Http\Controllers\Shared\OtpController;
use Illuminate\Support\Facades\Route;

Route::prefix('vendor')->as('vendor.')->group(function (): void {
    Route::post('auth/otp/request', [OtpController::class, 'request'])->defaults('actor_type', 'vendor')->middleware('throttle:otp_request')->name('auth.otp.request');
    Route::post('auth/otp/verify', [OtpController::class, 'verify'])->defaults('actor_type', 'vendor')->middleware('throttle:otp_verify')->name('auth.otp.verify');
    Route::middleware(['auth:sanctum', 'actor:vendor', 'throttle:api'])->group(function (): void {
        require base_path('routes/shared/account.php');
        Route::middleware('active')->group(function (): void {
            require base_path('routes/shared/payments.php');
        });
    });
});
