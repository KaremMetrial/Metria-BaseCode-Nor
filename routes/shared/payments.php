<?php

use App\Http\Controllers\Shared\PaymentController;
use App\Http\Controllers\Shared\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');
Route::get('wallet/{wallet}/transactions', [WalletController::class, 'history'])->whereNumber('wallet')->name('wallet.history');
Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
Route::get('payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment')->name('payments.show');
Route::post('payments', [PaymentController::class, 'store'])->middleware('throttle:login')->name('payments.store');
