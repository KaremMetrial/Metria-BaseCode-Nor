<?php

use App\Http\Controllers\Shared\ContactMessageController;
use App\Http\Controllers\Shared\ContactSettingController;
use App\Http\Controllers\Shared\PageController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function (): void {
    Route::get('pages', [PageController::class, 'index'])->name('pages.index');
    Route::get('pages/{slug}', [PageController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('pages.show');
    Route::get('contact-settings', [ContactSettingController::class, 'show'])->name('contact-settings.show');
    Route::post('contact-messages', [ContactMessageController::class, 'store'])->middleware('throttle:contact')->name('contact-messages.store');
});
