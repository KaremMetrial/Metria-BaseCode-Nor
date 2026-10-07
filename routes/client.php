<?php
use Illuminate\Support\Facades\Route;
Route::prefix('client')->as('client.')->group(function(): void {
 Route::post('auth/otp/request',[\App\Http\Controllers\Auth\OtpController::class,'request'])->defaults('actor_type','client')->middleware('throttle:otp_request')->name('auth.otp.request');
 Route::post('auth/otp/verify',[\App\Http\Controllers\Auth\OtpController::class,'verify'])->defaults('actor_type','client')->middleware('throttle:otp_verify')->name('auth.otp.verify');
 Route::middleware(['auth:sanctum','actor:client','throttle:api'])->group(function(): void {
  Route::post('auth/logout',\App\Http\Controllers\Auth\LogoutController::class)->name('auth.logout');
  Route::get('profile',[\App\Http\Controllers\Shared\ProfileController::class,'show'])->name('profile.show');
  Route::middleware('active')->group(function(): void {
   Route::get('wallet',[\App\Http\Controllers\Shared\WalletController::class,'index'])->name('wallet.index');
   Route::get('wallet/{wallet}/transactions',[\App\Http\Controllers\Shared\WalletController::class,'history'])->whereNumber('wallet')->name('wallet.history');
   Route::get('payments',[\App\Http\Controllers\Shared\PaymentController::class,'index'])->name('payments.index');
   Route::get('payments/{payment}',[\App\Http\Controllers\Shared\PaymentController::class,'show'])->whereNumber('payment')->name('payments.show');
   Route::post('payments',[\App\Http\Controllers\Shared\PaymentController::class,'store'])->middleware('throttle:login')->name('payments.store');
   Route::get('notifications',[\App\Http\Controllers\Shared\NotificationController::class,'index'])->name('notifications.index');
   Route::patch('notifications/{notification}/read',[\App\Http\Controllers\Shared\NotificationController::class,'read'])->whereUuid('notification')->name('notifications.read');
   Route::patch('profile',[\App\Http\Controllers\Shared\ProfileController::class,'update'])->name('profile.update');
   Route::post('profile/phone/request',[\App\Http\Controllers\Shared\PhoneChangeController::class,'request'])->middleware('throttle:otp_request')->name('profile.phone.request');
   Route::post('profile/phone/verify',[\App\Http\Controllers\Shared\PhoneChangeController::class,'verify'])->middleware('throttle:otp_verify')->name('profile.phone.verify');
   Route::post('realtime/token',\App\Http\Controllers\Shared\SocketTokenController::class)->middleware('throttle:login')->name('realtime.token');
   
  });
 });
});
