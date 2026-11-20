<?php

use App\Http\Controllers\Agenda\AgendaController;
use App\Http\Controllers\Agenda\AppointmentController;
use Illuminate\Support\Facades\Route;

// Módulo 4. Gestión de agenda y citas
Route::prefix('agenda')->name('agenda.')->group(function () {
    // Calendario (semana o día)
    Route::get('/', [AgendaController::class, 'index'])->name('index');

    // Citas
    Route::post('/citas', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::put('/citas/{appointment}/reagendar', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    Route::patch('/citas/{appointment}/cancelar', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::patch('/citas/{appointment}/iniciar', [AppointmentController::class, 'start'])->name('appointments.start');
    Route::patch('/citas/{appointment}/finalizar', [AppointmentController::class, 'finish'])->name('appointments.finish');
    Route::patch('/citas/{appointment}/no-asistio', [AppointmentController::class, 'noShow'])->name('appointments.no-show');
});
