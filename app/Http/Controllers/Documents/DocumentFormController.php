<?php

namespace App\Http\Controllers\Documents;

use App\Enums\DocumentTemplate;
use App\Http\Controllers\Controller;
use App\Models\ClinicalFile;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Services\Documents\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Lo común a los formularios de plantilla: paciente activo, doctores, nueva versión y vista previa. */
abstract class DocumentFormController extends Controller
{
    public function __construct(protected DocumentService $documents) {}

    abstract protected function template(): DocumentTemplate;

    protected function missingPatient(): RedirectResponse
    {
        return redirect()->route('documents.create')
            ->with('toast', ['type' => 'error', 'title' => 'Selecciona un paciente activo para crear el documento.']);
    }

    /** Documento de origen de una "nueva versión": misma plantilla y mismo paciente. */
    protected function sourceDocument(?string $id, Patient $patient): ?ClinicalFile
    {
        if (! Str::isUuid((string) $id)) {
            return null;
        }

        $document = ClinicalFile::documents()->where('patient_id', $patient->id)->find($id);

        return $document?->template() === $this->template() ? $document : null;
    }

    /** @return Collection<int, OrganizationMember> */
    protected function doctors(): Collection
    {
        return OrganizationMember::doctors()->with(['user', 'doctorProfile'])->get()
            ->sortBy(fn (OrganizationMember $m) => $m->displayName())
            ->values();
    }

    protected function defaultDoctorId(Collection $doctors, Patient $patient): ?string
    {
        return $doctors->firstWhere('id', $patient->assigned_doctor_member_id)?->id ?? $doctors->first()?->id;
    }

    /** PDF inline, sin guardar nada. */
    protected function pdfResponse(string $pdf): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vista-previa-'.$this->template()->value.'.pdf"',
        ]);
    }
}
