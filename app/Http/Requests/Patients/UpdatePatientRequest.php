<?php

namespace App\Http\Requests\Patients;

use App\Models\Patient;

class UpdatePatientRequest extends PatientRequest
{
    protected $errorBag = 'editPatient';

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    protected function ignoredPatient(): ?Patient
    {
        return $this->route('patient');
    }
}
