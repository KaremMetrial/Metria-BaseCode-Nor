<?php

use App\Http\Controllers\Shared\AppAssociationController;
use App\Http\Controllers\Shared\ContactMessageController;
use App\Http\Controllers\Shared\PageController;
use App\Http\Controllers\Shared\ShareLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['service' => config('app.name')]));

Route::get('/l/{code}', [ShareLinkController::class, 'web'])->where('code', '[a-f0-9]{32}')->middleware('throttle:api')->name('share.show');
Route::get('/.well-known/assetlinks.json', [AppAssociationController::class, 'android'])->name('links.android');
Route::get('/.well-known/apple-app-site-association', [AppAssociationController::class, 'apple'])->name('links.apple');

Route::get('/pages/{slug}', [PageController::class, 'web'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->middleware('throttle:api')->name('pages.show');

Route::post('/pages/contact-us/message', [ContactMessageController::class, 'web'])->middleware('throttle:contact')->name('contact.store');
