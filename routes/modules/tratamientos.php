<?php

use App\Http\Controllers\Treatments\TreatmentPlanController;
use Illuminate\Support\Facades\Route;

// Módulo 7. Gestión de tratamientos: pestaña del expediente (/pacientes/{patient}/tratamientos).
// scopeBindings(): {treatmentPlan} se busca con $patient->treatmentPlans(); si es de otro paciente, 404.
Route::prefix('pacientes/{patient}/tratamientos')->name('records.treatments.')->scopeBindings()->group(function () {
    Route::get('/', [TreatmentPlanController::class, 'index'])->name('index');
    Route::get('/nuevo', [TreatmentPlanController::class, 'create'])->name('create');
    Route::post('/', [TreatmentPlanController::class, 'store'])->name('store');
    Route::get('/{treatmentPlan}', [TreatmentPlanController::class, 'show'])->name('show');
    Route::get('/{treatmentPlan}/editar', [TreatmentPlanController::class, 'edit'])->name('edit');
    Route::put('/{treatmentPlan}', [TreatmentPlanController::class, 'update'])->name('update');
    Route::patch('/{treatmentPlan}/autorizar', [TreatmentPlanController::class, 'authorizePlan'])->name('authorize');
    Route::patch('/{treatmentPlan}/cancelar', [TreatmentPlanController::class, 'cancel'])->name('cancel');
    Route::post('/{treatmentPlan}/sesiones', [TreatmentPlanController::class, 'storeSession'])->name('sessions.store');
    Route::post('/{treatmentPlan}/sesiones/{session}/notas', [TreatmentPlanController::class, 'storeSessionNote'])
        ->withoutScopedBindings()->name('sessions.notes.store');
    Route::get('/{treatmentPlan}/pdf', [TreatmentPlanController::class, 'pdf'])->name('pdf');
});
