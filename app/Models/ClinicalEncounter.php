<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Consulta (módulo 6): una visita del paciente con un doctor. Agrupa las notas clínicas
 * de esa visita y puede venir de una cita de la Agenda (appointment_id).
 */
class ClinicalEncounter extends Model
{
    use BelongsToOrganization, HasUuids;

    const UPDATED_AT = null;   // la tabla solo tiene created_at

    protected $fillable = [
        'organization_id', 'medical_record_id', 'appointment_id', 'branch_id', 'doctor_member_id', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'doctor_member_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class, 'encounter_id');
    }
}
