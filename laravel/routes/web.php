<?php

use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

// Única interfaz: SemaOPS Mission Control (kiosko F11) + su feed JSON.
Route::get('/', [StatusController::class, 'index'])->name('home');
Route::get('/status.json', [StatusController::class, 'json'])->name('status.json');
