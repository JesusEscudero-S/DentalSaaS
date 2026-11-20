<?php

use App\Http\Controllers\Documents\ConsentController;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Documents\MedicalHistoryController;
use App\Http\Controllers\Documents\TreatmentAuthorizationController;
use Illuminate\Support\Facades\Route;

// Documentos: Historia clínica dental y Autorización de tratamiento (PDF).
Route::prefix('documentos')->name('documents.')->group(function () {
    Route::get('/', [DocumentController::class, 'index'])->name('index');
    Route::get('/nuevo', [DocumentController::class, 'create'])->name('create');
    Route::get('/pacientes', [DocumentController::class, 'patients'])->name('patients');
    Route::get('/plantillas/{template}/vista-previa', [DocumentController::class, 'preview'])->name('preview');

    Route::get('/historia-clinica/crear', [MedicalHistoryController::class, 'create'])->name('medical-history.create');
    Route::post('/historia-clinica', [MedicalHistoryController::class, 'store'])->name('medical-history.store');
    Route::post('/historia-clinica/vista-previa', [MedicalHistoryController::class, 'preview'])->name('medical-history.preview');

    Route::get('/autorizacion-tratamiento/crear', [TreatmentAuthorizationController::class, 'create'])->name('authorization.create');
    Route::post('/autorizacion-tratamiento', [TreatmentAuthorizationController::class, 'store'])->name('authorization.store');
    Route::post('/autorizacion-tratamiento/vista-previa', [TreatmentAuthorizationController::class, 'preview'])->name('authorization.preview');

    Route::whereUuid('document')->group(function () {
        Route::get('/{document}', [DocumentController::class, 'show'])->name('show');
        Route::get('/{document}/descargar', [DocumentController::class, 'download'])->name('download');
        Route::get('/{document}/ver', [DocumentController::class, 'stream'])->name('stream');
        Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
        Route::post('/{document}/firma', [ConsentController::class, 'store'])->name('consent.store');
        Route::get('/{document}/firma/escaneo', [ConsentController::class, 'scan'])->name('consent.scan');
    });
});
