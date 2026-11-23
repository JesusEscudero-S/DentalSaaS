<?php

namespace App\Services\Documents;

use App\Enums\ClinicalFileType;
use App\Exceptions\BusinessRuleException;
use App\Models\ClinicalFile;
use App\Models\ConsentAcceptance;
use App\Services\AuditLogger;
use App\Support\CurrentOrganization;
use App\Support\Ui;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Firma o aceptación de una Autorización de tratamiento (consent_acceptances). */
class ConsentService
{
    public function __construct(
        private AuditLogger $audit,
        private CurrentOrganization $current,
    ) {}

    /**
     * Solo se permite una firma por documento. content_hash guarda el SHA-256 del PDF: prueba qué contenido
     * se aceptó. El escaneo (si lo hay) es otro clinical_files en la carpeta del documento: firmado-{uuid}.ext.
     *
     * @param  array<string, mixed>  $data  method, accepted_by_name, accepted_at, witness_member_id
     */
    public function register(ClinicalFile $document, array $data, ?UploadedFile $scan = null): ConsentAcceptance
    {
        $written = null;

        try {
            return DB::transaction(function () use ($document, $data, $scan, &$written) {
                $locked = ClinicalFile::whereKey($document->getKey())->lockForUpdate()->firstOrFail();

                if (! $locked->template()?->requiresSignature()) {
                    throw new BusinessRuleException('Este documento no requiere firma.');
                }
                if ($locked->consentAcceptance()->exists()) {
                    throw new BusinessRuleException('Este consentimiento ya tiene una firma registrada.');
                }

                $scanFile = null;
                if ($scan) {
                    $contents = (string) $scan->get();
                    if ($contents === '') {
                        throw new BusinessRuleException('El documento firmado está vacío; vuelve a escanearlo.');
                    }
                    $extension = strtolower($scan->extension() ?: $scan->getClientOriginalExtension());
                    $written = $locked->documentDirectory().'/firmado-'.Str::uuid().'.'.$extension;

                    if (! Storage::disk(DocumentService::DISK)->put($written, $contents)) {
                        throw new RuntimeException('No se pudo guardar el documento firmado.');
                    }

                    $scanFile = ClinicalFile::create([
                        'patient_id' => $locked->patient_id,
                        'file_type' => ClinicalFileType::Consent,
                        'display_name' => 'Autorización firmada — '.$locked->patient?->folio.' — '.Ui::date(now()),
                        'mime_type' => $scan->getMimeType() ?: 'application/octet-stream',
                        'size_bytes' => strlen($contents),
                        'storage_key' => $written,
                        'sha256' => hash('sha256', $contents),
                        'uploaded_by_member_id' => $this->current->member()?->id,
                    ]);
                }

                $acceptance = ConsentAcceptance::create([
                    'patient_id' => $locked->patient_id,
                    'clinical_file_id' => $locked->id,
                    'accepted_at' => $data['accepted_at'],
                    'accepted_by_name' => $data['accepted_by_name'],
                    'method' => $data['method'],
                    'witness_member_id' => $data['witness_member_id'] ?? null,
                    'content_hash' => $locked->sha256,
                ]);

                $this->audit->log($acceptance, AuditLogger::INSERT, array_filter([
                    'clinical_file_id' => $locked->id,
                    'method' => $acceptance->method->value,
                    'scan_file_id' => $scanFile?->id,
                ]));

                return $acceptance;
            });
        } catch (Throwable $e) {
            if ($written) {
                Storage::disk(DocumentService::DISK)->delete($written);
            }

            throw $e;
        }
    }
}
