<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Expediente clínico (1:1 con el paciente, RN-E1). Se crea en blanco al registrar al paciente
 * (PatientService) y se gestiona en el módulo 6. La tabla no tiene updated_at: los cambios
 * quedan en audit_log.
 */
class MedicalRecord extends Model
{
    use BelongsToOrganization, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = ['organization_id', 'patient_id', 'allergies', 'medical_history'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(ClinicalEncounter::class);
    }

    /** RN-E3: si hay alergias o antecedentes, se muestra la alerta roja permanente. */
    public function hasAlerts(): bool
    {
        return filled($this->allergies) || filled($this->medical_history);
    }
}
