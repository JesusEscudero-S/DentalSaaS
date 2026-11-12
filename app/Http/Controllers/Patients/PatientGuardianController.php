<?php

namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\PatientGuardianRequest;
use App\Models\Patient;
use App\Models\PatientGuardian;
use App\Services\Patients\PatientGuardianService;
use Illuminate\Http\RedirectResponse;

/** Contactos de emergencia (RF 3.5 – 3.7). */
class PatientGuardianController extends Controller
{
    public function __construct(private PatientGuardianService $guardians) {}

    public function store(PatientGuardianRequest $request, Patient $patient): RedirectResponse
    {
        $this->guardians->create($patient, $request->validated());

        return $this->back($patient, 'Contacto guardado');
    }

    public function update(PatientGuardianRequest $request, Patient $patient, PatientGuardian $guardian): RedirectResponse
    {
        $this->guardians->update($guardian, $request->validated());

        return $this->back($patient, 'Contacto modificado');
    }

    public function destroy(Patient $patient, PatientGuardian $guardian): RedirectResponse
    {
        $this->authorize('pacientes.editar');

        $this->guardians->delete($guardian);

        return $this->back($patient, 'Contacto borrado');
    }

    private function back(Patient $patient, string $title): RedirectResponse
    {
        return redirect()
            ->route('patients.show', $patient)
            ->with('toast', ['type' => 'success', 'title' => $title, 'message' => $patient->full_name]);
    }
}
