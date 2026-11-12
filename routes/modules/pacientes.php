<?php

use App\Http\Controllers\Patients\PatientController;
use App\Http\Controllers\Patients\PatientGuardianController;
use Illuminate\Support\Facades\Route;

// Módulo 3. Gestión de pacientes y contactos
Route::prefix('pacientes')->name('patients.')->group(function () {
    Route::get('/', [PatientController::class, 'index'])->name('index');
    Route::post('/', [PatientController::class, 'store'])->name('store');
    Route::get('/exportar', [PatientController::class, 'export'])->name('export');
    Route::get('/{patient}', [PatientController::class, 'show'])->name('show');
    Route::put('/{patient}', [PatientController::class, 'update'])->name('update');
    Route::patch('/{patient}/desactivar', [PatientController::class, 'deactivate'])->name('deactivate');
    Route::patch('/{patient}/reactivar', [PatientController::class, 'reactivate'])->name('reactivate');

    Route::scopeBindings()->group(function () {
        Route::post('/{patient}/contactos', [PatientGuardianController::class, 'store'])->name('guardians.store');
        Route::put('/{patient}/contactos/{guardian}', [PatientGuardianController::class, 'update'])->name('guardians.update');
        Route::delete('/{patient}/contactos/{guardian}', [PatientGuardianController::class, 'destroy'])->name('guardians.destroy');
    });
});
