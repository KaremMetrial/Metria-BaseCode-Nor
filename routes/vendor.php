<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Shared\DeviceTokenController;
use App\Http\Controllers\Shared\NotificationController;
use App\Http\Controllers\Shared\PaymentController;
use App\Http\Controllers\Shared\PhoneChangeController;
use App\Http\Controllers\Shared\ProfileController;
use App\Http\Controllers\Shared\SocketTokenController;
use App\Http\Controllers\Shared\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('vendor')->as('vendor.')->group(function (): void {
    Route::post('auth/otp/request', [OtpController::class, 'request'])->defaults('actor_type', 'vendor')->middleware('throttle:otp_request')->name('auth.otp.request');
    Route::post('auth/otp/verify', [OtpController::class, 'verify'])->defaults('actor_type', 'vendor')->middleware('throttle:otp_verify')->name('auth.otp.verify');
    Route::middleware(['auth:sanctum', 'actor:vendor', 'throttle:api'])->group(function (): void {
        Route::post('auth/logout', LogoutController::class)->name('auth.logout');
        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::middleware('active')->group(function (): void {
            Route::post('devices', [DeviceTokenController::class, 'store'])->middleware('throttle:login')->name('devices.store');
            Route::delete('devices/{device}', [DeviceTokenController::class, 'destroy'])->whereNumber('device')->name('devices.destroy');
            Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');
            Route::get('wallet/{wallet}/transactions', [WalletController::class, 'history'])->whereNumber('wallet')->name('wallet.history');
            Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment')->name('payments.show');
            Route::post('payments', [PaymentController::class, 'store'])->middleware('throttle:login')->name('payments.store');
            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
            Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::post('profile/phone/request', [PhoneChangeController::class, 'request'])->middleware('throttle:otp_request')->name('profile.phone.request');
            Route::post('profile/phone/verify', [PhoneChangeController::class, 'verify'])->middleware('throttle:otp_verify')->name('profile.phone.verify');
            Route::post('realtime/token', SocketTokenController::class)->middleware('throttle:login')->name('realtime.token');

        });
    });
});
