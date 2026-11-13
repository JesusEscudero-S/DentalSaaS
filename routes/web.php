<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

/*
| Rutas de la aplicación: requieren sesión (auth) y una clínica activa (organization).
| Cada módulo registra sus rutas en routes/modules/<modulo>.php y se agrega aquí.
*/
Route::middleware(['auth', 'organization'])->group(function () {
    // El Dashboard aún no existe: la entrada es el listado de pacientes.
    Route::redirect('/', '/pacientes')->name('home');

    require __DIR__.'/modules/pacientes.php';
    // require __DIR__.'/modules/agenda.php';
    // require __DIR__.'/modules/servicios.php';
    // ...
});
