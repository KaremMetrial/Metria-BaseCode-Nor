<?php

use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ContactSettingController;
use App\Http\Controllers\Admin\PageController;
use Illuminate\Support\Facades\Route;

Route::get('pages', [PageController::class, 'index'])->middleware('can:pages.read')->name('pages.index');
Route::post('pages', [PageController::class, 'store'])->middleware('can:pages.manage')->name('pages.store');
Route::get('pages/{page}', [PageController::class, 'show'])->whereNumber('page')->middleware('can:pages.read')->name('pages.show');
Route::patch('pages/{page}', [PageController::class, 'update'])->whereNumber('page')->middleware('can:pages.manage')->name('pages.update');
Route::post('pages/{page}/publish', [PageController::class, 'publish'])->whereNumber('page')->middleware('can:pages.publish')->name('pages.publish');
Route::post('pages/{page}/archive', [PageController::class, 'archive'])->whereNumber('page')->middleware('can:pages.publish')->name('pages.archive');
Route::get('pages/{page}/revisions', [PageController::class, 'revisions'])->whereNumber('page')->middleware('can:pages.read')->name('pages.revisions');
Route::get('contact-settings', [ContactSettingController::class, 'show'])->middleware('can:contact.settings')->name('contact-settings.show');
Route::patch('contact-settings', [ContactSettingController::class, 'update'])->middleware('can:contact.settings')->name('contact-settings.update');
Route::get('contact-messages', [ContactMessageController::class, 'index'])->middleware('can:contact.read')->name('contact-messages.index');
Route::get('contact-messages/{message}', [ContactMessageController::class, 'show'])->whereNumber('message')->middleware('can:contact.read')->name('contact-messages.show');
Route::patch('contact-messages/{message}/status', [ContactMessageController::class, 'status'])->whereNumber('message')->middleware(['can:contact.read', 'can:contact.manage'])->name('contact-messages.status');
