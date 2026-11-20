<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio odontológico del catálogo. Lo gestiona el módulo 5 (Servicios odontológicos);
 * aquí solo se lee para el select "Tratamiento" de las notas clínicas.
 */
class Treatment extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = ['organization_id', 'category_id', 'name', 'duration_minutes', 'price', 'currency', 'status'];

    protected $casts = [
        'price' => 'decimal:4',
        'duration_minutes' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
