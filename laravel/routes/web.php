<?php

use App\Http\Controllers\ChaosLabController;
use App\Http\Controllers\StatusController;
use App\Http\Middleware\EnsureChaosLabEnabled;
use Illuminate\Support\Facades\Route;

// Única interfaz: SemaOPS Mission Control (kiosko F11) + su feed JSON.
Route::get('/', [StatusController::class, 'index'])->name('home');
Route::get('/status.json', [StatusController::class, 'json'])->name('status.json');

// SemaOPS Chaos Lab: solo existe con CHAOS_LAB_ENABLED=true (stack de clones).
Route::prefix('lab')
    ->name('lab.')
    ->middleware(EnsureChaosLabEnabled::class)
    ->group(function () {
        Route::get('/', [ChaosLabController::class, 'index'])->name('index');
        Route::get('/historial.csv', [ChaosLabController::class, 'export'])->name('export');

        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/state', [ChaosLabController::class, 'state'])->name('state');
            Route::post('/containers/{service}/{action}', [ChaosLabController::class, 'container'])
                ->whereIn('action', ['start', 'stop', 'restart'])->name('container');
            Route::post('/sites/{host}/mode', [ChaosLabController::class, 'mode'])
                ->where('host', '[A-Za-z0-9.-]+')->name('mode');
            Route::post('/scenarios/{key}', [ChaosLabController::class, 'scenario'])->name('scenario');
            Route::get('/experiments/{experiment}', [ChaosLabController::class, 'experiment'])->name('experiment');
            Route::post('/experiments/{experiment}/finish', [ChaosLabController::class, 'finish'])->name('finish');
            Route::post('/restore', [ChaosLabController::class, 'restore'])->name('restore');
            Route::post('/check', [ChaosLabController::class, 'check'])->name('check');
            Route::get('/alerts', [ChaosLabController::class, 'alerts'])->name('alerts');
            Route::get('/history', [ChaosLabController::class, 'history'])->name('history');
        });
    });
