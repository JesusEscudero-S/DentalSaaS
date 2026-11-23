<?php

namespace App\Services\Documents;

use App\Enums\DocumentTemplate;
use App\Enums\MedicalCondition;
use App\Exceptions\BusinessRuleException;
use App\Models\ClinicalFile;
use App\Models\MedicalRecord;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\Quote;
use App\Models\ToothFinding;
use App\Services\AuditLogger;
use App\Support\CurrentOrganization;
use App\Support\Ui;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Documentos de plantilla (Historia clínica, Autorización de tratamiento). Cada documento es un
 * clinical_files cuyo PDF vive en org/{org}/documentos/{template}/{document}/documento.pdf, junto a
 * respuestas.json con lo capturado en el formulario (D1).
 */
class DocumentService
{
    public const DISK = 'local';

    public function __construct(
        private AuditLogger $audit,
        private CurrentOrganization $current,
    ) {}

    /**
     * Historia clínica: genera el documento y (D3) actualiza alergias y antecedentes del expediente.
     *
     * @param  array<string, mixed>  $answers  StoreMedicalHistoryRequest::answers()
     */
    public function generateMedicalHistory(Patient $patient, OrganizationMember $doctor, array $answers, ?ClinicalFile $previous = null): ClinicalFile
    {
        return $this->generate(
            DocumentTemplate::MedicalHistory, $patient, $doctor, $answers, [], $previous,
            fn (ClinicalFile $file) => $this->syncMedicalRecord($patient, $answers, $file),
        );
    }

    /**
     * Autorización de tratamiento: queda "Pendiente de firma" y no toca el expediente.
     *
     * @param  array<string, mixed>  $answers  StoreTreatmentAuthorizationRequest::answers()
     */
    public function generateTreatmentAuthorization(Patient $patient, OrganizationMember $doctor, array $answers, ?ClinicalFile $previous = null): ClinicalFile
    {
        $answers = $this->withActivityDoctorNames($answers);

        return $this->generate(
            DocumentTemplate::TreatmentAuthorization, $patient, $doctor, $answers,
            $this->authorizationPdfData($patient, $answers), $previous,
        );
    }

    /** PDF de la autorización con lo capturado, sin guardar nada. */
    public function previewTreatmentAuthorization(Patient $patient, OrganizationMember $doctor, array $answers): string
    {
        $answers = $this->withActivityDoctorNames($answers);

        return $this->renderPdf(DocumentTemplate::TreatmentAuthorization, [
            'patient' => $patient, 'doctor' => $doctor, 'answers' => $answers, 'date' => now(),
            ...$this->authorizationPdfData($patient, $answers),
        ]);
    }

    /**
     * Odontograma vigente del expediente (superseded_at IS NULL), solo lectura.
     *
     * @return Collection<int, string> diente FDI => condición
     */
    public function currentFindings(Patient $patient): Collection
    {
        return ToothFinding::current()->where('patient_id', $patient->id)->orderBy('tooth_fdi')->get()
            // El modelo castea condition al enum ToothCondition; el PDF y la vista usan su valor en texto ('caries').
            ->mapWithKeys(fn (ToothFinding $finding) => [$finding->tooth_fdi => $finding->condition->value]);
    }

    /**
     * Renderiza el PDF, guarda PDF + respuestas.json y crea el clinical_files en una transacción.
     * Si algo falla, se borran los archivos ya escritos.
     *
     * @param  array<string, mixed>  $answers  se guardan en respuestas.json
     * @param  array<string, mixed>  $pdfData  variables extra para la vista del PDF
     * @param  (callable(ClinicalFile): void)|null  $afterCreate  pasos propios de la plantilla, dentro de la transacción
     */
    public function generate(
        DocumentTemplate $template,
        Patient $patient,
        OrganizationMember $doctor,
        array $answers,
        array $pdfData = [],
        ?ClinicalFile $previous = null,
        ?callable $afterCreate = null,
    ): ClinicalFile {
        $id = (string) Str::uuid();
        $directory = "org/{$patient->organization_id}/documentos/{$template->value}/{$id}";
        $generatedAt = now();

        $pdf = $this->renderPdf($template, [
            'patient' => $patient, 'doctor' => $doctor, 'answers' => $answers, 'date' => $generatedAt, ...$pdfData,
        ]);
        $json = json_encode([
            'template' => $template->value,
            'document_id' => $id,
            'patient_id' => $patient->id,
            'generated_at' => $generatedAt->toIso8601String(),
            'previous_document_id' => $previous?->id,
            'answers' => $answers,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $written = [];

        try {
            return DB::transaction(function () use ($template, $patient, $previous, $afterCreate, $id, $directory, $generatedAt, $pdf, $json, &$written) {
                foreach (['documento.pdf' => $pdf, 'respuestas.json' => $json] as $name => $contents) {
                    $path = "{$directory}/{$name}";
                    if (! Storage::disk(self::DISK)->put($path, $contents)) {
                        throw new RuntimeException("No se pudo guardar el archivo {$path}.");
                    }
                    $written[] = $path;
                }

                $file = new ClinicalFile([
                    'patient_id' => $patient->id,
                    'file_type' => $template->fileType(),
                    'display_name' => $template->label().' — '.$patient->folio.' — '.Ui::date($generatedAt),
                    'mime_type' => 'application/pdf',
                    'size_bytes' => strlen($pdf),
                    'storage_key' => "{$directory}/".ClinicalFile::DOCUMENT_FILENAME,
                    'sha256' => hash('sha256', $pdf),
                    'uploaded_by_member_id' => $this->current->member()?->id,
                ]);
                $file->id = $id;
                $file->save();

                if ($afterCreate) {
                    $afterCreate($file);
                }

                $this->audit->log($file, AuditLogger::INSERT, array_filter([
                    'template' => $template->value,
                    'patient_id' => $patient->id,
                    'previous_document_id' => $previous?->id,
                ]));

                return $file;
            });
        } catch (Throwable $e) {
            if ($written) {
                Storage::disk(self::DISK)->delete($written);
            }

            throw $e;
        }
    }

    /**
     * Respuestas guardadas en respuestas.json, para prellenar una "nueva versión".
     *
     * @return array<string, mixed>
     */
    public function answersOf(ClinicalFile $document): array
    {
        $path = $document->documentDirectory().'/respuestas.json';
        if (! Storage::disk(self::DISK)->exists($path)) {
            return [];
        }

        $data = json_decode((string) Storage::disk(self::DISK)->get($path), true);

        return is_array($data['answers'] ?? null) ? $data['answers'] : [];
    }

    /**
     * PDF (binario) de una plantilla. Sin datos sale el formato en blanco (vista previa).
     *
     * @param  array<string, mixed>  $data  variables de la vista: patient, doctor, answers, date…
     */
    public function renderPdf(DocumentTemplate $template, array $data = []): string
    {
        return Pdf::loadView($template->pdfView(), [
            'organization' => Organization::find($this->current->id()),
            'template' => $template,
            'patient' => null,
            'doctor' => null,
            'answers' => [],
            'date' => null,
            ...$data,
        ])->setPaper('letter')->setOption('isFontSubsettingEnabled', true)->output();
    }

    /** Baja lógica (deleted_at). Un consentimiento firmado no se elimina. */
    public function delete(ClinicalFile $document): void
    {
        DB::transaction(function () use ($document) {
            $locked = ClinicalFile::whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->consentAcceptance()->exists()) {
                throw new BusinessRuleException('No se puede eliminar un consentimiento firmado');
            }

            $locked->delete();
            $this->audit->log($locked, AuditLogger::DELETE, ['template' => $locked->template()?->value, 'patient_id' => $locked->patient_id]);
        });
    }

    /** @return array{findings: Collection<int, string>, quote: ?Quote} */
    private function authorizationPdfData(Patient $patient, array $answers): array
    {
        $quoteId = $answers['quote_id'] ?? null;

        return [
            'findings' => $this->currentFindings($patient),
            'quote' => $quoteId ? Quote::with('items')->where('patient_id', $patient->id)->find($quoteId) : null,
        ];
    }

    /** Guarda el nombre del estomatólogo de cada actividad (para el PDF y como respaldo en el JSON). */
    private function withActivityDoctorNames(array $answers): array
    {
        $ids = collect($answers['activities'] ?? [])->pluck('doctor_member_id')->filter()->unique();
        $names = $ids->isEmpty() ? collect() : OrganizationMember::with('user')->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (OrganizationMember $m) => [$m->id => $m->displayName()]);

        $answers['activities'] = array_map(
            fn (array $row) => [...$row, 'doctor_name' => $names[$row['doctor_member_id'] ?? ''] ?? null],
            $answers['activities'] ?? [],
        );

        return $answers;
    }

    /** D3: el expediente (módulo 6) refleja la última historia clínica. No se audita el texto clínico. */
    private function syncMedicalRecord(Patient $patient, array $answers, ClinicalFile $file): void
    {
        $record = MedicalRecord::firstOrCreate(['patient_id' => $patient->id]);
        $record->fill([
            'allergies' => $answers['allergies'] ?? null,
            'medical_history' => $this->medicalHistorySummary($answers, $file),
        ]);

        $changed = array_keys($record->getDirty());
        if ($changed) {
            $record->save();
            $this->audit->log($record, AuditLogger::UPDATE, ['fields' => $changed, 'document_id' => $file->id]);
        }
    }

    private function medicalHistorySummary(array $answers, ClinicalFile $file): string
    {
        $conditions = collect($answers['conditions'] ?? [])
            ->map(fn ($value) => MedicalCondition::tryFrom($value)?->label())
            ->filter()
            ->implode(', ');
        $treatment = $answers['under_medical_treatment'] ?? null;

        return implode("\n", [
            'Según la historia clínica del '.Ui::date($file->created_at).':',
            'Padecimientos: '.($conditions !== '' ? $conditions : 'ninguno').'.',
            'Tratamiento médico actual: '.(filled($treatment) ? $treatment : 'ninguno').'.',
        ]);
    }
}
