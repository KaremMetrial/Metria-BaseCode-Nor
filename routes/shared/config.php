<?php

use App\Http\Controllers\Shared\AppConfigController;
use Illuminate\Support\Facades\Route;

Route::get('config', [AppConfigController::class, 'show'])
    ->middleware(['throttle:api', 'cache.headers:private;no_cache;etag'])
    ->name('config.show');
