<?php

use App\Http\Controllers\Settings\SettingsController;
use Illuminate\Support\Facades\Route;

// Configuración (Figma): Usuarios y roles (/usuarios, módulo 1), Tratamientos y Mi perfil (/cambiar-contrasena).
Route::prefix('configuracion')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');
    Route::get('/tratamientos', [SettingsController::class, 'treatments'])->name('treatments');
});
