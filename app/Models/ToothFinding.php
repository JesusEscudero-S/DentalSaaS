<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hallazgo del odontograma. Lo administra el módulo 6 (Expedientes); aquí solo se lee. */
class ToothFinding extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'patient_id', 'tooth_fdi', 'dentition', 'condition', 'surfaces',
        'encounter_id', 'patient_treatment_id', 'author_member_id', 'recorded_at', 'superseded_at',
    ];

    protected $casts = [
        'tooth_fdi' => 'integer',
        'surfaces' => 'array',
        'recorded_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** Estado actual del odontograma (hallazgos no reemplazados). */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }
}
