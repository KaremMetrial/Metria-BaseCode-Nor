<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\WalletAdjustmentController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Shared\DeviceTokenController;
use App\Http\Controllers\Shared\NotificationController;
use App\Http\Controllers\Shared\PhoneChangeController;
use App\Http\Controllers\Shared\ProfileController;
use App\Http\Controllers\Shared\SocketTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->as('admin.')->group(function (): void {
    Route::post('auth/login', AdminLoginController::class)->middleware('throttle:login')->name('auth.login');
    Route::middleware(['auth:sanctum', 'actor:admin', 'throttle:api'])->group(function (): void {
        Route::post('auth/logout', LogoutController::class)->name('auth.logout');
        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::middleware('active')->group(function (): void {
            Route::post('devices', [DeviceTokenController::class, 'store'])->middleware('throttle:login')->name('devices.store');
            Route::delete('devices/{device}', [DeviceTokenController::class, 'destroy'])->whereNumber('device')->name('devices.destroy');
            Route::post('wallets/{wallet}/adjustments', WalletAdjustmentController::class)->whereNumber('wallet')->middleware('can:wallets.adjust')->name('wallet.adjust');
            Route::post('payments/{payment}/refunds', RefundController::class)->whereNumber('payment')->middleware('can:payments.refund')->name('payments.refund');
            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
            Route::get('categories', [CategoryController::class, 'index'])->middleware('can:categories.read')->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->middleware('can:categories.create')->name('categories.store');
            Route::patch('categories/{category}', [CategoryController::class, 'update'])->whereNumber('category')->middleware('can:categories.update')->name('categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->whereNumber('category')->middleware('can:categories.delete')->name('categories.destroy');
            Route::get('users', [UserManagementController::class, 'index'])->middleware('can:users.read')->name('users.index');
            Route::patch('users/{user}/status', [UserManagementController::class, 'status'])->whereNumber('user')->name('users.status');
            Route::post('users/{user}/roles', [UserManagementController::class, 'role'])->whereNumber('user')->name('users.roles');
            Route::get('payments', [FinanceController::class, 'payments'])->middleware('can:payments.read')->name('payments.index');
            Route::get('wallets', [FinanceController::class, 'wallets'])->middleware('can:wallets.read')->name('wallets.index');
            Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::post('profile/phone/request', [PhoneChangeController::class, 'request'])->middleware('throttle:otp_request')->name('profile.phone.request');
            Route::post('profile/phone/verify', [PhoneChangeController::class, 'verify'])->middleware('throttle:otp_verify')->name('profile.phone.verify');
            Route::post('realtime/token', SocketTokenController::class)->middleware('throttle:login')->name('realtime.token');
            require base_path('routes/admin/locations.php');
        });
    });
});
