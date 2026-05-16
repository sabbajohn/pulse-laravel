<?php

use Illuminate\Support\Facades\Route;
use Sabbajohn\PulseLaravel\Http\Controllers\DashboardController;
use Sabbajohn\PulseLaravel\Http\Controllers\PageController;
use Sabbajohn\PulseLaravel\Http\Controllers\ProxyController;

if (config('pulse.enabled')) {
    Route::middleware(config('pulse.middleware', ['web', 'auth']))
        ->prefix(config('pulse.route_prefix', 'pulse'))
        ->name('pulse.')
        ->group(function () {
            Route::get('/', DashboardController::class)->name('dashboard');
            Route::get('/sections/{section}', PageController::class)->name('section');
            Route::any('/api/{path}', ProxyController::class)->where('path', '.*')->name('api.proxy');
        });
}
