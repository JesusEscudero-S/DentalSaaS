<?php

use App\Http\Controllers\Agenda\AgendaController;
use Illuminate\Support\Facades\Route;

// Módulo 4. Gestión de agenda
Route::prefix('agenda')->name('agenda.')->group(function () {
    Route::get('/', [AgendaController::class, 'index'])->name('index');
});
