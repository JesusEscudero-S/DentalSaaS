<?php

namespace App\Providers;

use App\Support\CurrentOrganization;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
        // Permisos del esquema (permissions.module + permissions.action), ej. "pacientes.crear".
        // Se resuelven contra el rol del miembro en la organización activa.
        Gate::before(function ($user, string $ability) {
            if (preg_match('/^[a-z_]+\.[a-z_]+$/', $ability)) {
                return app(CurrentOrganization::class)->can($ability);
            }

            return null;
        });
    }
}
