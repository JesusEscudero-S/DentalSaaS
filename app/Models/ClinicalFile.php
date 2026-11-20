<?php

namespace App\Models;

use App\Enums\ClinicalFileType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Archivo del expediente (radiografía, foto, consentimiento…). El archivo vive en el disco
 * privado `local` (storage/app/clinical/…), nunca en public/: se descarga pasando por el controlador.
 * Eliminar = baja lógica (deleted_at); el archivo físico se conserva.
 */
class ClinicalFile extends Model
{
    use BelongsToOrganization, HasUuids, SoftDeletes;

    const UPDATED_AT = null;

    public const DISK = 'local';

    public const MAX_KB = 10240;   // 10 MB

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

    /** "PDF", "JPG", "PNG" (Figma) */
    public function extensionLabel(): string
    {
        return match ($this->mime_type) {
            'application/pdf' => 'PDF',
            'image/png' => 'PNG',
            'image/jpeg' => 'JPG',
            default => strtoupper(pathinfo($this->storage_key, PATHINFO_EXTENSION)),
        };
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /** "1.2 MB" */
    public function humanSize(): string
    {
        $kb = $this->size_bytes / 1024;

        return $kb >= 1024 ? number_format($kb / 1024, 1).' MB' : number_format(max($kb, 1)).' KB';
    }
}
