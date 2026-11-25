<?php

use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

// Módulo 1. Gestión de usuarios
Route::prefix('usuarios')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/pdf', [UserController::class, 'pdf'])->name('pdf');
    Route::put('/{member}', [UserController::class, 'update'])->name('update');
    Route::patch('/{member}/desactivar', [UserController::class, 'deactivate'])->name('deactivate');
    Route::patch('/{member}/reactivar', [UserController::class, 'reactivate'])->name('reactivate');
    Route::patch('/{member}/restablecer-contrasena', [UserController::class, 'resetPassword'])->name('reset-password');
});
