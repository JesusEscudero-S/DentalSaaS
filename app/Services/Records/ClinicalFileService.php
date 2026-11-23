<?php

namespace App\Services\Records;

use App\Enums\ClinicalFileType;
use App\Models\ClinicalFile;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Archivos del expediente (RF 6.7 Subir / 6.8 Eliminar).
 *
 * Se guardan en el disco privado `local`: storage/app/clinical/<clínica>/<paciente>/<uuid>.<ext>.
 * El nombre físico es un UUID (nunca el nombre que trae el usuario) y se guarda su SHA-256.
 */
class ClinicalFileService
{
    public function __construct(private AuditLogger $audit) {}

    public function filesFor(Patient $patient)
    {
        return ClinicalFile::query()
            ->where('patient_id', $patient->id)
            ->with('uploadedBy.user')
            ->latest('created_at')
            ->get();
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, ClinicalFile>
     */
    public function store(Patient $patient, array $files, ClinicalFileType $type, ?string $description, OrganizationMember $member): array
    {
        $saved = [];
        $written = [];   // rutas físicas ya escritas, para borrarlas si algo falla

        try {
            DB::transaction(function () use ($patient, $files, $type, $description, $member, &$saved, &$written) {
                foreach ($files as $i => $file) {
                    $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
                    $key = "clinical/{$patient->organization_id}/{$patient->id}/".Str::uuid().".{$extension}";

                    Storage::disk(ClinicalFile::DISK)->putFileAs(dirname($key), $file, basename($key));
                    $written[] = $key;

                    $clinicalFile = ClinicalFile::create([
                        'patient_id' => $patient->id,
                        'file_type' => $type,
                        'display_name' => $this->displayName($file, $description, $i, count($files)),
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                        'storage_key' => $key,
                        'sha256' => hash_file('sha256', $file->getRealPath()),
                        'uploaded_by_member_id' => $member->id,
                    ]);

                    $this->audit->log($clinicalFile, AuditLogger::INSERT, ['file_type' => $type->value, 'size_bytes' => $clinicalFile->size_bytes]);
                    $saved[] = $clinicalFile;
                }
            });
        } catch (Throwable $e) {
            // La transacción deshizo las filas; los archivos físicos hay que borrarlos a mano.
            Storage::disk(ClinicalFile::DISK)->delete($written);
            throw $e;
        }

        return $saved;
    }

    /**
     * RF 6.8: baja lógica. El archivo físico se conserva por si hay que recuperarlo.
     * Los documentos generados por el módulo Documentos (y sus escaneos firmados) no se borran aquí:
     * se gestionan allá, donde está la regla "un consentimiento firmado no se elimina".
     */
    public function delete(ClinicalFile $file): void
    {
        if ($file->isTemplateDocument()) {
            throw new DomainException('Este documento se gestiona en el módulo Documentos.');
        }

        DB::transaction(function () use ($file) {
            $file->delete();
            $this->audit->log($file, AuditLogger::DELETE, ['display_name' => $file->display_name]);
        });
    }

    /** Descarga (o vista en el navegador con $inline) pasando por la app: el disco no es público. */
    public function response(ClinicalFile $file, bool $inline = false): StreamedResponse
    {
        $disk = Storage::disk(ClinicalFile::DISK);
        abort_unless($disk->exists($file->storage_key), 404);

        $name = Str::slug(pathinfo($file->display_name, PATHINFO_FILENAME) ?: 'archivo').'.'.strtolower($file->extensionLabel() === 'JPG' ? 'jpg' : $file->extensionLabel());

        return $inline
            ? $disk->response($file->storage_key, $name, ['Content-Type' => $file->mime_type])
            : $disk->download($file->storage_key, $name, ['Content-Type' => $file->mime_type]);
    }

    /** Nombre visible: la descripción corta del PDF si la hay; si no, el nombre original del archivo. */
    private function displayName(UploadedFile $file, ?string $description, int $index, int $total): string
    {
        if (filled($description)) {
            return Str::limit(trim($description).($total > 1 ? ' ('.($index + 1).')' : ''), 250, '');
        }

        return Str::limit($file->getClientOriginalName(), 250, '');
    }
}
