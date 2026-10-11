<?php

use App\Http\Controllers\Shared\DeviceTokenController;
use App\Http\Controllers\Shared\LogoutController;
use App\Http\Controllers\Shared\NotificationController;
use App\Http\Controllers\Shared\PhoneChangeController;
use App\Http\Controllers\Shared\ProfileController;
use App\Http\Controllers\Shared\SocketTokenController;
use Illuminate\Support\Facades\Route;

Route::post('auth/logout', LogoutController::class)->name('auth.logout');
Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');

Route::middleware('active')->group(function (): void {
    Route::post('devices', [DeviceTokenController::class, 'store'])->middleware('throttle:login')->name('devices.store');
    Route::delete('devices/{device}', [DeviceTokenController::class, 'destroy'])->whereNumber('device')->name('devices.destroy');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/phone/request', [PhoneChangeController::class, 'request'])->middleware('throttle:otp_request')->name('profile.phone.request');
    Route::post('profile/phone/verify', [PhoneChangeController::class, 'verify'])->middleware('throttle:otp_verify')->name('profile.phone.verify');
    Route::post('realtime/token', SocketTokenController::class)->middleware('throttle:login')->name('realtime.token');
});
