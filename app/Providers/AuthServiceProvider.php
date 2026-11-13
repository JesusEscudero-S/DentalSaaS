<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * Por ahora NO hay roles ni permisos: cualquier usuario con sesión y acceso a la
 * clínica puede usar todos los módulos. Los permisos se implementan en el
 * módulo 2 (Gestión de roles) con las tablas roles / permissions / role_permissions.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    public function boot(): void
    {
        //
    }
}
