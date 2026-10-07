<?php
use Illuminate\Support\Facades\Route;
Route::prefix('admin')->as('admin.')->group(function(): void {
 Route::post('auth/login', \App\Http\Controllers\Auth\AdminLoginController::class)->middleware('throttle:login')->name('auth.login');
 Route::middleware(['auth:sanctum','actor:admin','throttle:api'])->group(function(): void {
  Route::post('auth/logout',\App\Http\Controllers\Auth\LogoutController::class)->name('auth.logout');
  Route::get('profile',[\App\Http\Controllers\Shared\ProfileController::class,'show'])->name('profile.show');
  Route::middleware('active')->group(function(): void {
   Route::post('wallets/{wallet}/adjustments',\App\Http\Controllers\Admin\WalletAdjustmentController::class)->whereNumber('wallet')->middleware('can:wallets.adjust')->name('wallet.adjust');
   Route::post('payments/{payment}/refunds',\App\Http\Controllers\Admin\RefundController::class)->whereNumber('payment')->middleware('can:payments.refund')->name('payments.refund');
   Route::get('notifications',[\App\Http\Controllers\Shared\NotificationController::class,'index'])->name('notifications.index');
   Route::patch('notifications/{notification}/read',[\App\Http\Controllers\Shared\NotificationController::class,'read'])->whereUuid('notification')->name('notifications.read');
   Route::get('categories',[\App\Http\Controllers\Admin\CategoryController::class,'index'])->middleware('can:categories.read')->name('categories.index');
   Route::post('categories',[\App\Http\Controllers\Admin\CategoryController::class,'store'])->middleware('can:categories.create')->name('categories.store');
   Route::patch('categories/{category}',[\App\Http\Controllers\Admin\CategoryController::class,'update'])->whereNumber('category')->middleware('can:categories.update')->name('categories.update');
   Route::delete('categories/{category}',[\App\Http\Controllers\Admin\CategoryController::class,'destroy'])->whereNumber('category')->middleware('can:categories.delete')->name('categories.destroy');
   Route::patch('profile',[\App\Http\Controllers\Shared\ProfileController::class,'update'])->name('profile.update');
   Route::post('profile/phone/request',[\App\Http\Controllers\Shared\PhoneChangeController::class,'request'])->middleware('throttle:otp_request')->name('profile.phone.request');
   Route::post('profile/phone/verify',[\App\Http\Controllers\Shared\PhoneChangeController::class,'verify'])->middleware('throttle:otp_verify')->name('profile.phone.verify');
   Route::post('realtime/token',\App\Http\Controllers\Shared\SocketTokenController::class)->middleware('throttle:login')->name('realtime.token');
   require base_path('routes/admin/locations.php');
  });
 });
});
