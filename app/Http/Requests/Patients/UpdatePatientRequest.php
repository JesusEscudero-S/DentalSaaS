<?php

namespace App\Http\Requests\Patients;

use App\Models\Patient;

class UpdatePatientRequest extends PatientRequest
{
    protected $errorBag = 'editPatient';

    public function authorize(): bool
    {
        return $this->user()->can('pacientes.editar');
    }

    protected function ignoredPatient(): ?Patient
    {
        return $this->route('patient');
    }
}
