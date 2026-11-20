<?php

namespace App\Http\Controllers\Documents;

use App\Enums\DocumentTemplate;
use App\Http\Requests\Documents\StoreMedicalHistoryRequest;
use App\Models\ClinicalFile;
use App\Models\OrganizationMember;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Historia Clínica Dental: formulario, vista previa y generación. */
class MedicalHistoryController extends DocumentFormController
{
    protected function template(): DocumentTemplate
    {
        return DocumentTemplate::MedicalHistory;
    }

    /** ?paciente={uuid} y, para una nueva versión, &desde={documento}. */
    public function create(Request $request): View|RedirectResponse
    {
        $patient = Patient::active()->with('medicalRecord')->find((string) $request->query('paciente'));
        if (! $patient) {
            return $this->missingPatient();
        }

        $doctors = $this->doctors();
        $source = $this->sourceDocument($request->query('desde'), $patient);

        $defaults = [
            'doctor_member_id' => $this->defaultDoctorId($doctors, $patient),
            'address' => null,
            'occupation' => null,
            'chief_complaint' => null,
            'bad_previous_experience' => false,
            'bad_experience_reason' => null,
            'uses_toothbrush' => true,
            'brushings_per_day' => null,
            'extraction_complications' => [],
            'under_medical_treatment' => null,
            'conditions' => [],
            'allergies' => $patient->medicalRecord?->allergies,
            'blood_pressure' => null,
            'pulse' => null,
            'respiration' => null,
        ];

        return view('documents.medical-history.form', [
            'patient' => $patient,
            'doctors' => $doctors,
            'source' => $source,
            'form' => array_merge($defaults, $source ? $this->documents->answersOf($source) : []),
        ]);
    }

    public function store(StoreMedicalHistoryRequest $request): RedirectResponse
    {
        [$patient, $doctor, $previous] = $this->context($request);

        $document = $this->documents->generateMedicalHistory($patient, $doctor, $request->answers(), $previous);

        return redirect()
            ->route('documents.show', $document)
            ->with('toast', ['type' => 'success', 'title' => 'Documento generado correctamente', 'message' => $document->display_name]);
    }

    /** PDF con lo capturado, sin guardar nada (se abre en otra pestaña). */
    public function preview(StoreMedicalHistoryRequest $request): Response
    {
        [$patient, $doctor] = $this->context($request);

        return $this->pdfResponse($this->documents->renderPdf($this->template(), [
            'patient' => $patient, 'doctor' => $doctor, 'answers' => $request->answers(), 'date' => now(),
        ]));
    }

    /** @return array{0: Patient, 1: OrganizationMember, 2: ?ClinicalFile} */
    private function context(StoreMedicalHistoryRequest $request): array
    {
        $patient = Patient::active()->findOrFail($request->validated('patient_id'));
        $doctor = OrganizationMember::doctors()->with(['user', 'doctorProfile'])->findOrFail($request->validated('doctor_member_id'));

        return [$patient, $doctor, $this->sourceDocument($request->validated('previous_document_id'), $patient)];
    }
}
