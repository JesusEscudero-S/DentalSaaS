<?php

namespace App\Providers;

use App\Support\CurrentOrganization;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Sanctum no se usa y su tabla no forma parte del esquema de la BD.
        Sanctum::ignoreMigrations();

        $this->app->scoped(CurrentOrganization::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('components.ui.pagination');
    }
}
