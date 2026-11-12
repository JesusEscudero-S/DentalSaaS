<?php

namespace App\Models\Scopes;

use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $current = app(CurrentOrganization::class);

        // Sin contexto (consola, seeders) no se filtra; las rutas web siempre lo tienen
        // porque el middleware ResolveOrganization lo exige.
        if ($current->has()) {
            $builder->where($model->qualifyColumn('organization_id'), $current->id());
        }
    }
}
