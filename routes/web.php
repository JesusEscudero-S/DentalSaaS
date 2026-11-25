<?php

use App\Http\Controllers\Users\PasswordChangeController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

/*
| Rutas de la aplicación: requieren sesión (auth) y una clínica activa (organization).
| session.current cierra las sesiones abiertas de un usuario cuando le restablecen la contraseña (módulo 1).
| Cada módulo registra sus rutas en routes/modules/<modulo>.php y se agrega aquí.
*/
Route::middleware(['auth', 'organization', 'session.current'])->group(function () {
    // El Dashboard aún no existe: la entrada es el listado de pacientes.
    Route::redirect('/', '/pacientes')->name('home');

    // Módulo 1, P7: cada usuario cambia su propia contraseña.
    Route::get('/cambiar-contrasena', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('/cambiar-contrasena', [PasswordChangeController::class, 'update'])->name('password.change.update');

    require __DIR__.'/modules/usuarios.php';
    require __DIR__.'/modules/pacientes.php';
    require __DIR__.'/modules/agenda.php';
    require __DIR__.'/modules/expediente.php';
    require __DIR__.'/modules/tratamientos.php';
    // require __DIR__.'/modules/servicios.php';
    // ...
    require __DIR__.'/modules/finanzas.php';
    require __DIR__.'/modules/documentos.php';
});
