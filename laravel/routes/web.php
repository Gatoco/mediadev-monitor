<?php

use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

// Demo pública read-only (sin auth) — antes del redirect para que gane.
Route::get('/demo', [DemoController::class, 'page'])->name('demo.page');
Route::get('/status.json', [DemoController::class, 'json'])->name('demo.json');

// Entrar al dominio = ir directo al panel de monitoreo (la demo).
Route::redirect('/', '/demo');