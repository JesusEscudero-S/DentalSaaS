<?php

namespace App\Models;

use App\Enums\ClinicalFileType;
use App\Enums\DocumentStatus;
use App\Enums\DocumentTemplate;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Archivo clínico (radiografías, consentimientos, documentos generados…). Los documentos de plantilla
 * se reconocen por su storage_key: org/{org}/documentos/{template}/{document}/documento.pdf.
 * `deleted_at` es la baja lógica.
 */
class ClinicalFile extends Model
{
    use BelongsToOrganization, HasUuids, SoftDeletes;

    const UPDATED_AT = null;

    public const DOCUMENT_FILENAME = 'documento.pdf';

    protected $fillable = [
        'organization_id', 'patient_id', 'file_type', 'display_name', 'mime_type', 'size_bytes',
        'storage_key', 'sha256', 'uploaded_by_member_id', 'encounter_id',
    ];

    protected $casts = [
        'file_type' => ClinicalFileType::class,
        'size_bytes' => 'integer',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'uploaded_by_member_id');
    }

    /** Firma/aceptación registrada sobre este documento. */
    public function consentAcceptance(): HasOne
    {
        return $this->hasOne(ConsentAcceptance::class);
    }

    /** Plantilla que generó el archivo, según el segmento {template} del storage_key. */
    public function template(): ?DocumentTemplate
    {
        if (! preg_match('#^org/[^/]+/documentos/([^/]+)/[^/]+/'.preg_quote(self::DOCUMENT_FILENAME, '#').'$#', (string) $this->storage_key, $m)) {
            return null;
        }

        return DocumentTemplate::tryFrom($m[1]);
    }

    /** Generado / Pendiente de firma / Firmado; null si el archivo no es un documento de plantilla. */
    public function documentStatus(): ?DocumentStatus
    {
        $template = $this->template();

        return match (true) {
            $template === null => null,
            ! $template->requiresSignature() => DocumentStatus::Generated,
            $this->consentAcceptance !== null => DocumentStatus::Signed,
            default => DocumentStatus::PendingSignature,
        };
    }

    /** "historia-clinica-PAC-0001-2026-11-20.pdf" */
    public function downloadName(): string
    {
        $extension = pathinfo($this->storage_key, PATHINFO_EXTENSION) ?: 'pdf';
        $prefix = $this->template()?->value ?? 'archivo';

        return $prefix.'-'.($this->patient?->folio ?? 'paciente').'-'.$this->created_at?->format('Y-m-d').'.'.$extension;
    }

    /** Escaneo firmado de un documento de plantilla (firmado-{uuid}.ext en su carpeta). */
    public function signedScan(): ?self
    {
        if ($this->template() === null) {
            return null;
        }

        return static::where('patient_id', $this->patient_id)
            ->where('storage_key', 'like', $this->documentDirectory().'/firmado-%')
            ->latest('created_at')
            ->first();
    }

    /** Carpeta del documento (donde viven el PDF, respuestas.json y los escaneos firmados). */
    public function documentDirectory(): string
    {
        return dirname($this->storage_key);
    }

    /** Solo documentos generados desde una plantilla. */
    public function scopeDocuments(Builder $query): Builder
    {
        return $query->where('storage_key', 'like', 'org/%/documentos/%/'.self::DOCUMENT_FILENAME);
    }
}
