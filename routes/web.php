<?php

use App\Http\Controllers\Shared\ContactMessageController;
use App\Http\Controllers\Shared\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['service' => config('app.name')]));

Route::get('/pages/{slug}', [PageController::class, 'web'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->middleware('throttle:api')->name('pages.show');

Route::post('/pages/contact-us/message', [ContactMessageController::class, 'web'])->middleware('throttle:contact')->name('contact.store');
