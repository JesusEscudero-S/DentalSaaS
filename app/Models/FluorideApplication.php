<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Aplicación de flúor (RF 6.6). Tabla nueva del módulo 6 (migración 2026_11_20_000001).
 * El año de control no se guarda: es el año de applied_on.
 */
class FluorideApplication extends Model
{
    use BelongsToOrganization, HasUuids, SoftDeletes;

    const UPDATED_AT = null;

    protected $fillable = ['organization_id', 'patient_id', 'applied_on', 'applied_by_member_id', 'notes'];

    protected $casts = [
        'applied_on' => 'date',
    ];

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'applied_by_member_id');
    }
}
