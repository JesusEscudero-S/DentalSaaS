<?php

namespace App\Http\Requests\Patients;

use App\Enums\ConsentMethod;
use App\Models\Patient;
use Illuminate\Validation\Rule;

class StorePatientRequest extends PatientRequest
{
    protected $errorBag = 'createPatient';

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    protected function ignoredPatient(): ?Patient
    {
        return null;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $signedBy = $this->input('consent_signed_by');

        $this->merge([
            'consent_signed_by' => is_string($signedBy) && trim($signedBy) !== ''
                ? mb_strtoupper(preg_replace('/\s+/', ' ', trim($signedBy)))
                : $this->input('full_name'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // RN-P4: la responsiva firmada es obligatoria para dar de alta.
            'consent_signed' => ['accepted'],
            'consent_signed_by' => ['required', 'string', 'max:250'],
            'consent_method' => ['required', Rule::enum(ConsentMethod::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'consent_signed.accepted' => 'El paciente debe firmar la responsiva para registrarse.',
        ];
    }
}
