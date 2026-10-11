<?php

use App\Http\Controllers\Shared\CategoryController;
use Illuminate\Support\Facades\Route;

Route::get('categories', [CategoryController::class, 'index'])->middleware('throttle:api')->name('categories.index');
Route::get('categories/{category}', [CategoryController::class, 'show'])->whereNumber('category')->middleware('throttle:api')->name('categories.show');
