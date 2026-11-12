<?php

namespace App\Http\Requests\Patients;

use App\Enums\PatientSex;
use App\Enums\TaxRegime;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas comunes de alta y edición de paciente (RF 3.1 y 3.3).
 */
abstract class PatientRequest extends FormRequest
{
    private const FISCAL_FIELDS = ['rfc', 'legal_name', 'tax_regime_code', 'fiscal_postal_code'];

    abstract protected function ignoredPatient(): ?Patient;

    protected function prepareForValidation(): void
    {
        $upper = fn ($v) => is_string($v) && $v !== '' ? mb_strtoupper(preg_replace('/\s+/', ' ', trim($v))) : $v;

        $this->merge([
            'full_name' => $upper($this->input('full_name')),
            'phone' => preg_replace('/\D/', '', (string) $this->input('phone')) ?: null,
            'email' => $this->filled('email') ? mb_strtolower(trim($this->input('email'))) : null,
            'rfc' => $this->filled('rfc') ? mb_strtoupper(preg_replace('/\s+/', '', $this->input('rfc'))) : null,
            'legal_name' => $upper($this->input('legal_name')),
            'fiscal_postal_code' => $this->filled('fiscal_postal_code') ? trim($this->input('fiscal_postal_code')) : null,
            'sex' => $this->input('sex') ?: PatientSex::Unspecified->value,
            'assigned_doctor_member_id' => $this->input('assigned_doctor_member_id') ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();
        $fiscalWith = fn (string $field) => 'required_with:'.implode(',', array_diff(self::FISCAL_FIELDS, [$field]));

        return [
            'full_name' => ['required', 'string', 'max:250'],
            'birth_date' => ['required', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'sex' => ['required', Rule::enum(PatientSex::class)],
            'phone' => [
                'required', 'digits:10',
                // RN-P1: teléfono único por organización (también contra pacientes inactivos).
                Rule::unique('patients', 'phone')
                    ->where('organization_id', $organizationId)
                    ->whereNull('deleted_at')
                    ->ignore($this->ignoredPatient()?->id),
            ],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'assigned_doctor_member_id' => [
                'nullable', 'uuid',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! OrganizationMember::doctors()->whereKey($value)->exists()) {
                        $fail('El doctor seleccionado no es válido.');
                    }
                },
            ],

            // Datos fiscales (opcionales, pero si se captura uno se requieren los cuatro: la BD los exige).
            'rfc' => ['nullable', $fiscalWith('rfc'), 'regex:/^([A-ZÑ&]{3,4})\d{6}([A-Z\d]{3})$/u'],
            'legal_name' => ['nullable', $fiscalWith('legal_name'), 'string', 'max:300'],
            'tax_regime_code' => ['nullable', $fiscalWith('tax_regime_code'), Rule::enum(TaxRegime::class)],
            'fiscal_postal_code' => ['nullable', $fiscalWith('fiscal_postal_code'), 'digits:5'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.unique' => 'El teléfono ya está asignado a otro paciente.',
            'phone.digits' => 'El teléfono debe tener exactamente 10 dígitos numéricos.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser posterior a hoy.',
            'rfc.regex' => 'El RFC no tiene un formato válido.',
            '*.required_with' => 'Completa todos los datos fiscales o deja la sección vacía.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'full_name' => 'nombre completo',
            'birth_date' => 'fecha de nacimiento',
            'sex' => 'sexo',
            'phone' => 'teléfono',
            'email' => 'email',
            'assigned_doctor_member_id' => 'doctor asignado',
            'rfc' => 'RFC',
            'legal_name' => 'razón social',
            'tax_regime_code' => 'régimen fiscal',
            'fiscal_postal_code' => 'código postal fiscal',
            'consent_signed' => 'responsiva',
            'consent_signed_by' => 'nombre de quien firmó',
            'consent_method' => 'método de firma',
        ];
    }
}
