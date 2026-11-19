<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\belongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'patient_id',
        'doctor_member_id',
        'treatment_id',
        'starts_at',
        'ends_at',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'status' => AppointmentStatus::class,
    ];

    // relationships
    public function patient(): belongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): belongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'doctor_member_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class);
    }

    public function branch(): belongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
