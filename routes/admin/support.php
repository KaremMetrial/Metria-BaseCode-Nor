<?php

use App\Http\Controllers\Admin\SupportConversationController;
use Illuminate\Support\Facades\Route;

Route::prefix('support/conversations')->as('support.conversations.')->whereNumber('conversation')->group(function (): void {
    Route::get('', [SupportConversationController::class, 'index'])->middleware(['can:support.read'])->name('index');
    Route::get('{conversation}', [SupportConversationController::class, 'show'])->middleware(['can:support.read'])->name('show');
    Route::get('{conversation}/messages', [SupportConversationController::class, 'messages'])->middleware(['can:support.read'])->name('messages');
    Route::post('{conversation}/messages', [SupportConversationController::class, 'send'])->middleware(['can:support.read', 'can:support.reply', 'throttle:support'])->name('send');
    Route::patch('{conversation}/read', [SupportConversationController::class, 'read'])->middleware(['can:support.read', 'throttle:support'])->name('read');
    Route::patch('{conversation}/assignment', [SupportConversationController::class, 'assignment'])->middleware(['can:support.read', 'can:support.manage', 'throttle:support'])->name('assignment');
    Route::patch('{conversation}/status', [SupportConversationController::class, 'status'])->middleware(['can:support.read', 'can:support.manage', 'throttle:support'])->name('status');
});
