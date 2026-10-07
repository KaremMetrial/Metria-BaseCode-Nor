<?php

use App\Http\Controllers\Admin\MyFatoorahController;
use App\Services\Payments\MyFatoorahCatalog;
use Illuminate\Support\Facades\Route;

Route::prefix('integrations/myfatoorah')->as('myfatoorah.')->middleware('can:myfatoorah.manage')->group(function (): void {
    Route::get('capabilities', [MyFatoorahController::class, 'capabilities'])->name('capabilities');
    Route::get('operations', [MyFatoorahController::class, 'operations'])->name('operations');
    Route::get('operations/{operationRecord}', [MyFatoorahController::class, 'operation'])->whereNumber('operationRecord')->name('operation');
    Route::patch('operations/{operationRecord}/resolution', [MyFatoorahController::class, 'resolve'])->whereNumber('operationRecord')->name('resolution');
    Route::get('entities', [MyFatoorahController::class, 'entities'])->name('entities');
    Route::get('entities/{entity}', [MyFatoorahController::class, 'entity'])->whereNumber('entity')->name('entity');
    foreach (app(MyFatoorahCatalog::class)->all() as $operation) {
        Route::match([$operation['http_method']], $operation['route'], [MyFatoorahController::class, 'execute'])
            ->defaults('operation', $operation['name'])->name($operation['name']);
    }
});
