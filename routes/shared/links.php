<?php

use App\Http\Controllers\Shared\ShareLinkController;
use Illuminate\Support\Facades\Route;

Route::get('links/{code}', [ShareLinkController::class, 'show'])->where('code', '[a-f0-9]{32}')->middleware('throttle:api')->name('links.show');
