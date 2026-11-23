<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Catálogo de servicios odontológicos (módulo 5). */
class Treatment extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id', 'category_id', 'name', 'duration_minutes', 'sat_product_key', 'sat_unit_key',
        'price', 'currency', 'tax_profile_id', 'status',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'price' => 'decimal:4',
    ];
}
