<?php
use Illuminate\Support\Facades\Route;
Route::post('webhooks/payments/{provider}',App\Http\Controllers\WebhookController::class)->middleware('throttle:webhooks')->name('webhooks.payments');
