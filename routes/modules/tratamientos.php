<?php

use App\Http\Controllers\Treatments\TreatmentPlanController;
use Illuminate\Support\Facades\Route;

// Módulo 7. Gestión de tratamientos: pestaña del expediente (/pacientes/{patient}/tratamientos).
// Cada plan es un presupuesto: scopeBindings() busca {quote} con $patient->quotes(); si es de otro paciente, 404.
Route::prefix('pacientes/{patient}/tratamientos')->name('records.treatments.')->scopeBindings()->group(function () {
    Route::get('/', [TreatmentPlanController::class, 'index'])->name('index');
    Route::get('/nuevo', [TreatmentPlanController::class, 'create'])->name('create');
    Route::post('/', [TreatmentPlanController::class, 'store'])->name('store');
    Route::get('/{quote}', [TreatmentPlanController::class, 'show'])->name('show');
    Route::get('/{quote}/editar', [TreatmentPlanController::class, 'edit'])->name('edit');
    Route::put('/{quote}', [TreatmentPlanController::class, 'update'])->name('update');
    Route::patch('/{quote}/autorizar', [TreatmentPlanController::class, 'authorizePlan'])->name('authorize');
    Route::patch('/{quote}/cancelar', [TreatmentPlanController::class, 'cancel'])->name('cancel');
    Route::post('/{quote}/sesiones', [TreatmentPlanController::class, 'storeSession'])->name('sessions.store');
    Route::get('/{quote}/pdf', [TreatmentPlanController::class, 'pdf'])->name('pdf');
});
