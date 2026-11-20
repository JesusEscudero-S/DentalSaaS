<?php

use App\Http\Controllers\Agenda\AgendaController;
use App\Http\Controllers\Agenda\AppointmentController;
use Illuminate\Support\Facades\Route;

// Módulo 4. Gestión de agenda y citas
Route::prefix('agenda')->name('agenda.')->group(function () {
    Route::get('/', [AgendaController::class, 'index'])->name('index');
    Route::post('/citas', [AppointmentController::class, 'store'])->name('appointments.store');
});
