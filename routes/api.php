<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Middleware\AvailabilityToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/availability')->name('availability.')->group(function () {
    Route::post('/snapshots', [AvailabilityController::class, 'store'])
        ->middleware([AvailabilityToken::class.':write', 'throttle:12,1'])->name('store');
    Route::middleware([AvailabilityToken::class.':read', 'throttle:60,1'])->group(function () {
        Route::get('/devices', [AvailabilityController::class, 'latest'])->name('latest');
        Route::get('/history', [AvailabilityController::class, 'history'])->name('history');
        Route::get('/transitions', [AvailabilityController::class, 'transitions'])->name('transitions');
        Route::get('/report', [AvailabilityController::class, 'report'])->name('report');
    });
});
