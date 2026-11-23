<?php

use App\Http\Controllers\DemoController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

// Única interfaz: SemaOPS Mission Control (kiosko F11) + su feed JSON.
Route::get('/', [StatusController::class, 'index'])->name('home');
Route::get('/status.json', [StatusController::class, 'json'])->name('status.json');

// Control remoto de la demo desde el celular (404 sin DEMO_TOKEN).
Route::get('/demo', [DemoController::class, 'panel'])->name('demo');
Route::get('/demo/state', [DemoController::class, 'state']);
Route::post('/demo/command', [DemoController::class, 'command']);
