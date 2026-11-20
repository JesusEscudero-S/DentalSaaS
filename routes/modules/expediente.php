<?php

use App\Http\Controllers\Records\ClinicalFileController;
use App\Http\Controllers\Records\ClinicalNoteController;
use App\Http\Controllers\Records\MedicalRecordController;
use App\Http\Controllers\Records\OdontogramController;
use Illuminate\Support\Facades\Route;

// Módulo 6. Gestión de expedientes. Son pestañas del perfil del paciente (/pacientes/{patient}).
// scopeBindings(): {clinicalFile} y {fluorideApplication} deben pertenecer al {patient} de la URL
// (Laravel los busca con $patient->clinicalFiles() / ->fluorideApplications()); si no, 404.
Route::prefix('pacientes/{patient}')->name('records.')->scopeBindings()->group(function () {
    // Historial y antecedentes
    Route::get('/historial', [MedicalRecordController::class, 'history'])->name('history');
    Route::put('/antecedentes', [MedicalRecordController::class, 'updateHistory'])->name('medical-history.update');

    // Notas clínicas (diagnóstico dental). "nueva" va antes de {note} para que no se confunda con un id.
    Route::get('/notas', [ClinicalNoteController::class, 'index'])->name('notes.index');
    Route::get('/notas/nueva', [ClinicalNoteController::class, 'create'])->name('notes.create');
    Route::post('/notas', [ClinicalNoteController::class, 'store'])->name('notes.store');
    Route::get('/notas/{note}', [ClinicalNoteController::class, 'show'])->withoutScopedBindings()->name('notes.show');
    Route::put('/notas/{note}', [ClinicalNoteController::class, 'update'])->withoutScopedBindings()->name('notes.update');
    Route::patch('/notas/{note}/firmar', [ClinicalNoteController::class, 'sign'])->withoutScopedBindings()->name('notes.sign');
    Route::post('/notas/{note}/adendas', [ClinicalNoteController::class, 'addendum'])->withoutScopedBindings()->name('notes.addendum');

    // Archivos
    Route::get('/archivos', [ClinicalFileController::class, 'index'])->name('files.index');
    Route::post('/archivos', [ClinicalFileController::class, 'store'])->name('files.store');
    Route::get('/archivos/{clinicalFile}', [ClinicalFileController::class, 'show'])->name('files.show');
    Route::delete('/archivos/{clinicalFile}', [ClinicalFileController::class, 'destroy'])->name('files.destroy');

    // Odontograma y flúor
    Route::get('/odontograma', [OdontogramController::class, 'index'])->name('odontogram.index');
    Route::post('/odontograma', [OdontogramController::class, 'store'])->name('odontogram.store');
    Route::post('/fluor', [OdontogramController::class, 'storeFluoride'])->name('fluoride.store');
    Route::delete('/fluor/{fluorideApplication}', [OdontogramController::class, 'destroyFluoride'])->name('fluoride.destroy');
});
