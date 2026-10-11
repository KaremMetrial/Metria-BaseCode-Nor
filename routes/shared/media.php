<?php

use App\Http\Controllers\Shared\MediaController;
use Illuminate\Support\Facades\Route;

Route::post('media', [MediaController::class, 'store'])->middleware('throttle:media')->name('media.store');
Route::get('media/{media}', [MediaController::class, 'show'])->whereUuid('media')->name('media.show');
Route::get('media/{media}/download', [MediaController::class, 'download'])->whereUuid('media')->name('media.download');
Route::delete('media/{media}', [MediaController::class, 'destroy'])->whereUuid('media')->name('media.destroy');
