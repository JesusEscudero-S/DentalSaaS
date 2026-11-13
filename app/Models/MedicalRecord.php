<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Expediente clínico (1:1 con el paciente). Se gestiona en el módulo 6. */
class MedicalRecord extends Model
{
    use BelongsToOrganization, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = ['organization_id', 'patient_id', 'allergies', 'medical_history'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
