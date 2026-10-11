<?php

use App\Http\Controllers\Client\SupportConversationController;
use Illuminate\Support\Facades\Route;

Route::prefix('support/conversations')->as('support.conversations.')->whereNumber('conversation')->group(function (): void {
    Route::get('', [SupportConversationController::class, 'index'])->name('index');
    Route::get('{conversation}', [SupportConversationController::class, 'show'])->name('show');
    Route::get('{conversation}/messages', [SupportConversationController::class, 'messages'])->name('messages');
    Route::post('{conversation}/messages', [SupportConversationController::class, 'send'])->middleware(['throttle:support'])->name('send');
    Route::patch('{conversation}/read', [SupportConversationController::class, 'read'])->middleware(['throttle:support'])->name('read');
    Route::post('', [SupportConversationController::class, 'store'])->middleware(['throttle:support'])->name('store');
    Route::post('{conversation}/close', [SupportConversationController::class, 'close'])->middleware(['throttle:support'])->name('close');
});
