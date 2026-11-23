<?php

namespace App\Models;

use App\Enums\Dentition;
use App\Enums\ToothCondition;
use App\Enums\ToothSurface;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hallazgo del odontograma (módulo 6). No se sobrescribe: al cambiar el estado de una pieza se cierra
 * el hallazgo vigente (superseded_at) y se crea uno nuevo. Así queda la historia de cada diente.
 * La BD garantiza un solo hallazgo vigente por pieza (columna generada current_key + UNIQUE).
 * Documentos lo lee para el odontograma del PDF.
 */
class ToothFinding extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false;   // usa recorded_at / superseded_at

    protected $fillable = [
        'organization_id', 'patient_id', 'tooth_fdi', 'dentition', 'condition', 'surfaces',
        'encounter_id', 'patient_treatment_id', 'author_member_id', 'recorded_at', 'superseded_at',
    ];

    protected $casts = [
        'tooth_fdi' => 'integer',
        'dentition' => Dentition::class,
        'condition' => ToothCondition::class,
        'surfaces' => 'array',
        'recorded_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'author_member_id');
    }

    /** Solo los hallazgos vigentes (el estado actual de cada pieza). */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    /** "Caries (Mesial, Oclusal)" */
    public function description(): string
    {
        $surfaces = collect($this->surfaces ?? [])
            ->map(fn ($value) => ToothSurface::tryFrom($value)?->label() ?? $value)
            ->join(', ');

        return $this->condition->label().($surfaces ? " ({$surfaces})" : '');
    }
}
