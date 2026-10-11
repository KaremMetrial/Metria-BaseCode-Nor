<?php

use App\Http\Controllers\Client\ReferralController;
use App\Http\Controllers\Client\ShareLinkController;
use Illuminate\Support\Facades\Route;

Route::post('share-links', [ShareLinkController::class, 'store'])->middleware('throttle:sharing')->name('share-links.store');
Route::delete('share-links/{code}', [ShareLinkController::class, 'destroy'])->where('code', '[a-f0-9]{32}')->name('share-links.destroy');
Route::get('referrals', [ReferralController::class, 'show'])->name('referrals.show');
Route::post('referrals/link', [ReferralController::class, 'store'])->middleware('throttle:sharing')->name('referrals.store');
Route::post('referrals/claim', [ReferralController::class, 'claim'])->middleware('throttle:referrals')->name('referrals.claim');
Route::get('referrals/history', [ReferralController::class, 'index'])->name('referrals.index');
