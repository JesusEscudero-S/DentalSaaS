<?php

namespace App\Models;

use App\Enums\GuardianRelationship;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Contacto de emergencia / tutor del paciente (RF 3.5 – 3.7). */
class PatientGuardian extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false;

    protected $fillable = ['organization_id', 'patient_id', 'full_name', 'relationship', 'phone', 'email', 'is_primary'];

    protected $casts = [
        'relationship' => GuardianRelationship::class,
        'is_primary' => 'boolean',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
