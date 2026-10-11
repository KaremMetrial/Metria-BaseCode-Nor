<?php

use App\Http\Controllers\Client\AccountController;
use App\Http\Controllers\Client\OnboardingController;
use App\Http\Controllers\Client\ReauthenticationController;
use App\Http\Controllers\Shared\OtpController;
use Illuminate\Support\Facades\Route;

Route::prefix('client')->as('client.')->group(function (): void {
    Route::get('auth/requirements', [AccountController::class, 'requirements'])->middleware('throttle:api')->name('auth.requirements');
    Route::post('auth/otp/request', [OtpController::class, 'request'])->defaults('actor_type', 'client')->middleware('throttle:otp_request')->name('auth.otp.request');
    Route::post('auth/otp/verify', [OtpController::class, 'verify'])->defaults('actor_type', 'client')->middleware('throttle:otp_verify')->name('auth.otp.verify');
    Route::middleware(['auth:sanctum', 'actor:client', 'throttle:api'])->group(function (): void {
        require base_path('routes/shared/account.php');
        Route::middleware('active')->group(function (): void {
            Route::post('auth/complete-profile', OnboardingController::class)->name('auth.complete-profile');
            Route::post('auth/reauthenticate/request', [ReauthenticationController::class, 'request'])->middleware('throttle:otp_request')->name('auth.reauthenticate.request');
            Route::post('auth/reauthenticate/verify', [ReauthenticationController::class, 'verify'])->middleware('throttle:otp_verify')->name('auth.reauthenticate.verify');
            Route::delete('account', [AccountController::class, 'destroy'])->middleware('throttle:login')->name('account.destroy');
            require base_path('routes/shared/payments.php');
        });
    });
});
