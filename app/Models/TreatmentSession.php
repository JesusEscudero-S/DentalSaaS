<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Módulo 7: sesión de tratamiento (hoja "Desarrollo de tratamiento"). No se borra (RN-T4). */
class TreatmentSession extends Model
{
    use BelongsToOrganization, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'plan_id', 'encounter_id', 'doctor_member_id', 'performed_on', 'activities', 'created_by_user_id',
    ];

    protected $casts = ['performed_on' => 'date'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'plan_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'doctor_member_id');
    }

    public function procedures(): BelongsToMany
    {
        return $this->belongsToMany(PatientTreatment::class, 'treatment_session_items', 'session_id', 'patient_treatment_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TreatmentSessionNote::class, 'session_id')->orderBy('created_at');
    }
}
