<?php

namespace App\Http\Controllers\Documents;

use App\Enums\DocumentTemplate;
use App\Http\Requests\Documents\StoreTreatmentAuthorizationRequest;
use App\Models\ClinicalFile;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\PatientTreatment;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Autorización de Tratamiento: formulario (parte4.html), vista previa y generación. */
class TreatmentAuthorizationController extends DocumentFormController
{
    protected function template(): DocumentTemplate
    {
        return DocumentTemplate::TreatmentAuthorization;
    }

    /** ?paciente={uuid} y, para una nueva versión, &desde={documento}. */
    public function create(Request $request): View|RedirectResponse
    {
        $patient = Patient::active()->find((string) $request->query('paciente'));
        if (! $patient) {
            return $this->missingPatient();
        }

        $doctors = $this->doctors();
        $doctorId = $this->defaultDoctorId($doctors, $patient);
        $source = $this->sourceDocument($request->query('desde'), $patient);
        $quotes = Quote::with('items')
            ->where('patient_id', $patient->id)
            ->whereIn('status', ['enviado', 'aprobado'])
            ->orderByDesc('quote_date')
            ->get();
        $activities = $this->treatmentActivities($patient, $doctorId);

        $defaults = [
            'doctor_member_id' => $doctorId,
            'fluoride' => [],
            'diagnoses' => [],
            'activities' => $activities,
            'quote_id' => $quotes->firstWhere('status', 'aprobado')?->id,
        ];

        return view('documents.authorization.form', [
            'patient' => $patient,
            'doctors' => $doctors,
            'source' => $source,
            'quotes' => $quotes,
            'findings' => $this->documents->currentFindings($patient),
            'prefilledActivities' => ! $source && $activities !== [],
            'form' => array_merge($defaults, $source ? $this->documents->answersOf($source) : []),
        ]);
    }

    public function store(StoreTreatmentAuthorizationRequest $request): RedirectResponse
    {
        [$patient, $doctor, $previous] = $this->context($request);

        $document = $this->documents->generateTreatmentAuthorization($patient, $doctor, $request->answers(), $previous);

        return redirect()
            ->route('documents.show', $document)
            ->with('toast', ['type' => 'success', 'title' => 'Documento generado correctamente', 'message' => $document->display_name]);
    }

    public function preview(StoreTreatmentAuthorizationRequest $request): Response
    {
        [$patient, $doctor] = $this->context($request);

        return $this->pdfResponse($this->documents->previewTreatmentAuthorization($patient, $doctor, $request->answers()));
    }

    /** @return array{0: Patient, 1: OrganizationMember, 2: ?ClinicalFile} */
    private function context(StoreTreatmentAuthorizationRequest $request): array
    {
        $patient = Patient::active()->findOrFail($request->validated('patient_id'));
        $doctor = OrganizationMember::doctors()->with(['user', 'doctorProfile'])->findOrFail($request->validated('doctor_member_id'));

        return [$patient, $doctor, $this->sourceDocument($request->validated('previous_document_id'), $patient)];
    }

    /**
     * "Desarrollo de tratamiento" prellenado con los tratamientos en progreso o realizados (módulo 7).
     *
     * @return list<array{date: ?string, description: string, doctor_member_id: ?string}>
     */
    private function treatmentActivities(Patient $patient, ?string $doctorId): array
    {
        return PatientTreatment::with('treatment')
            ->where('patient_id', $patient->id)
            ->whereIn('status', ['en_progreso', 'realizado'])
            ->get()
            ->sortBy(fn (PatientTreatment $t) => $t->completed_at ?? $t->started_at)
            ->map(fn (PatientTreatment $t) => [
                'date' => ($t->completed_at ?? $t->started_at)?->toDateString(),
                'description' => ($t->treatment?->name ?? 'Tratamiento')
                    .($t->tooth_fdi ? ' · diente '.$t->tooth_fdi : '')
                    .($t->status === 'en_progreso' ? ' (en progreso)' : ''),
                'doctor_member_id' => $doctorId,
            ])
            ->values()
            ->all();
    }
}
