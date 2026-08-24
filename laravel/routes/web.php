<?php

use Illuminate\Support\Facades\Route;

// La home del scaffold (welcome) se eliminó: el panel Filament es la UI.
Route::redirect('/', '/admin');
