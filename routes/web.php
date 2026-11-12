<?php

use App\Http\Controllers\Auth\DevLoginController;
use Illuminate\Support\Facades\Route;

/*
| Acceso temporal de desarrollo (solo APP_ENV=local) hasta implementar el módulo 1.
| La tabla users no tiene contraseña: se elige un usuario sembrado y su organización.
*/
if (app()->environment('local', 'testing')) {
    Route::get('/login', [DevLoginController::class, 'show'])->name('login');
    Route::post('/login/{member}', [DevLoginController::class, 'login'])->name('dev.login');
}

Route::post('/logout', [DevLoginController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'organization'])->group(function () {
    // El dashboard aún no existe: la entrada es el listado de pacientes.
    Route::redirect('/', '/pacientes')->name('home');

    require __DIR__.'/modules/pacientes.php';
});
