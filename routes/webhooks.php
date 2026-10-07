<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/payments/{provider}', WebhookController::class)->middleware('throttle:webhooks')->name('webhooks.payments');
