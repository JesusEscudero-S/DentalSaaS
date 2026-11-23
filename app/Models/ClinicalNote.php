<?php

namespace App\Models;

use App\Enums\NoteStatus;
use App\Enums\NoteType;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Ui;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Nota clínica (módulo 6). Es el "diagnóstico dental" del PDF (RF 6.4 / 6.5).
 *
 * - Nota simple: el texto va en `objective` (procedimiento y hallazgos).
 * - Nota SOAP: Subjetivo, Objetivo, Análisis y Plan.
 * - Borrador → se puede editar. Firmada → inmutable (trigger de la BD); se corrige con adendas.
 */
class ClinicalNote extends Model
{
    use BelongsToOrganization, HasUuids;

    const UPDATED_AT = null;

    /** Secciones SOAP: columna => [título, ayuda] (textos de Figma). */
    public const SOAP_SECTIONS = [
        'subjective' => ['Subjetivo (S)', 'Motivo de consulta y síntomas referidos por el paciente.'],
        'objective' => ['Objetivo (O)', 'Hallazgos clínicos, exploración y pruebas.'],
        'analysis' => ['Análisis (A)', 'Diagnóstico o impresión clínica.'],
        'plan' => ['Plan (P)', 'Tratamiento, indicaciones y seguimiento.'],
    ];

    protected $fillable = [
        'organization_id', 'encounter_id', 'note_type', 'author_member_id', 'responsible_doctor_member_id',
        'treatment_id', 'tooth_fdi', 'appointment_id', 'subjective', 'objective', 'analysis', 'plan',
        'prescription_text', 'status', 'signed_at', 'signed_by_member_id', 'content_hash', 'signature_mechanism',
    ];

    protected $casts = [
        'note_type' => NoteType::class,
        'status' => NoteStatus::class,
        'signed_at' => 'datetime',
        'tooth_fdi' => 'integer',
    ];

    // Relaciones

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'encounter_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'author_member_id');
    }

    public function responsibleDoctor(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'responsible_doctor_member_id');
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'signed_by_member_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function addenda(): HasMany
    {
        return $this->hasMany(ClinicalNoteAddendum::class, 'note_id')->orderBy('version_number');
    }

    // Reglas

    /** ¿Es una nota del expediente de este paciente? (la nota llega al paciente vía consulta → expediente) */
    public function belongsToPatient(Patient $patient): bool
    {
        return $this->encounter?->medicalRecord?->patient_id === $patient->id;
    }

    public function isSigned(): bool
    {
        return $this->status === NoteStatus::Firmada;
    }

    public function isSoap(): bool
    {
        return $this->note_type === NoteType::Soap;
    }

    /**
     * Huella SHA-256 del contenido clínico al firmar. Si alguien cambiara la fila directo en la BD,
     * el hash ya no coincidiría (verifyIntegrity).
     */
    public function computeHash(): string
    {
        return hash('sha256', json_encode([
            $this->id, $this->encounter_id, $this->note_type?->value, $this->responsible_doctor_member_id,
            $this->treatment_id, $this->tooth_fdi, $this->subjective, $this->objective, $this->analysis, $this->plan,
        ], JSON_UNESCAPED_UNICODE));
    }

    public function verifyIntegrity(): bool
    {
        return $this->isSigned() && hash_equals((string) $this->content_hash, $this->computeHash());
    }

    /** Texto corto para listados: en SOAP el análisis (diagnóstico); en simple, la nota. */
    public function summary(int $limit = 220): string
    {
        $text = $this->isSoap()
            ? ($this->analysis ?: $this->plan ?: $this->objective ?: $this->subjective)
            : $this->objective;

        return Str::limit(trim((string) $text), $limit);
    }

    /** "28 may 2026 · Dra. Mariana Reyes" (Figma) */
    public function heading(): string
    {
        return Ui::date($this->encounter->occurred_at).' · '.$this->responsibleDoctor->displayName();
    }
}
