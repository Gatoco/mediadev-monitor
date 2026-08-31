<?php

use App\Http\Controllers\DemoController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

// Vistas públicas read-only (sin auth) — antes del redirect para que ganen.
Route::get('/demo', [DemoController::class, 'page'])->name('demo.page');
Route::get('/demo.json', [DemoController::class, 'json'])->name('demo.json');

// Mission Control: estado en vivo (polling 5s). /live = mismo panel en modo kiosko.
Route::get('/status', [StatusController::class, 'index'])->name('status.page');
Route::get('/live', [StatusController::class, 'index'])->name('status.live');
Route::get('/status.json', [StatusController::class, 'json'])->name('status.json');

Route::redirect('/', '/status');
