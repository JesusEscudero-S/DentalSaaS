<?php

namespace App\Http\Requests\Documents;

use App\Enums\ExtractionComplication;
use App\Enums\MedicalCondition;
use App\Enums\PatientSex;
use App\Models\OrganizationMember;
use App\Models\Patient;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Historia Clínica Dental (parte3.html). Se usa también para la vista previa sin guardar. */
class StoreMedicalHistoryRequest extends FormRequest
{
    protected $errorBag = 'medicalHistory';

    private const TEXT_FIELDS = [
        'address', 'occupation', 'chief_complaint', 'bad_experience_reason', 'under_medical_treatment', 'allergies', 'blood_pressure',
    ];

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (self::TEXT_FIELDS as $field) {
            $value = $this->input($field);
            $clean[$field] = is_string($value) && trim($value) !== '' ? preg_replace('/[ \t]+/', ' ', trim($value)) : null;
        }
        foreach (['brushings_per_day', 'pulse', 'respiration'] as $field) {
            $clean[$field] = $this->filled($field) ? $this->input($field) : null;
        }

        $clean['blood_pressure'] = $clean['blood_pressure'] !== null ? str_replace(' ', '', $clean['blood_pressure']) : null;
        $clean['conditions'] = array_values(array_unique((array) $this->input('conditions', [])));
        $clean['extraction_complications'] = array_values(array_unique((array) $this->input('extraction_complications', [])));

        if ($this->input('bad_previous_experience') === '0') {
            $clean['bad_experience_reason'] = null;
        }
        if ($this->input('uses_toothbrush') === '0') {
            $clean['brushings_per_day'] = null;
        }

        $this->merge($clean);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'patient_id' => [
                'required', 'uuid',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! Patient::active()->whereKey($value)->exists()) {
                        $fail('El paciente no existe o está inactivo.');
                    }
                },
            ],
            'doctor_member_id' => [
                'required', 'uuid',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! OrganizationMember::doctors()->whereKey($value)->exists()) {
                        $fail('El doctor seleccionado no es válido.');
                    }
                },
            ],
            'previous_document_id' => ['nullable', 'uuid'],

            'address' => ['nullable', 'string', 'max:300'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'chief_complaint' => ['required', 'string', 'max:1000'],

            'bad_previous_experience' => ['required', 'boolean'],
            'bad_experience_reason' => [Rule::requiredIf(fn () => $this->boolean('bad_previous_experience')), 'nullable', 'string', 'max:500'],
            'uses_toothbrush' => ['required', 'boolean'],
            'brushings_per_day' => ['nullable', 'integer', 'between:0,10'],
            'extraction_complications' => ['array'],
            'extraction_complications.*' => [Rule::enum(ExtractionComplication::class)],

            'under_medical_treatment' => ['nullable', 'string', 'max:1000'],
            'conditions' => ['array'],
            'conditions.*' => [Rule::enum(MedicalCondition::class)],
            'allergies' => [Rule::requiredIf(fn () => $this->hasCondition(MedicalCondition::AllergicReactions)), 'nullable', 'string', 'max:1000'],

            'blood_pressure' => ['nullable', 'regex:/^\d{2,3}\/\d{2,3}$/'],
            'pulse' => ['nullable', 'integer', 'between:30,220'],
            'respiration' => ['nullable', 'integer', 'between:5,60'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->hasCondition(MedicalCondition::Pregnancy)) {
                return;
            }

            $patient = Patient::find($this->input('patient_id'));
            if ($patient && $patient->sex !== PatientSex::Female) {
                $validator->errors()->add('conditions', 'El embarazo solo aplica a pacientes de sexo femenino.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'bad_previous_experience.required' => 'Indica si tuvo una experiencia desagradable.',
            'bad_experience_reason.required' => 'Indica por qué fue desagradable la experiencia.',
            'uses_toothbrush.required' => 'Indica si utiliza cepillo dental.',
            'brushings_per_day.between' => 'Las veces al día deben estar entre 0 y 10.',
            'allergies.required' => 'Describe las alergias del paciente.',
            'blood_pressure.regex' => 'La presión arterial debe tener el formato 120/80.',
            'pulse.between' => 'El pulso debe estar entre 30 y 220 lpm.',
            'respiration.between' => 'La respiración debe estar entre 5 y 60 rpm.',
            'conditions.*.enum' => 'Seleccionaste un padecimiento no válido.',
            'extraction_complications.*.enum' => 'Seleccionaste una complicación no válida.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'doctor_member_id' => 'doctor responsable',
            'address' => 'domicilio',
            'occupation' => 'ocupación',
            'chief_complaint' => 'motivo de consulta',
            'brushings_per_day' => 'veces al día',
            'under_medical_treatment' => 'tratamiento médico',
            'allergies' => 'alergias',
            'pulse' => 'pulso',
            'respiration' => 'respiración',
        ];
    }

    /**
     * Respuestas tipadas que se guardan en respuestas.json y se imprimen en el PDF.
     *
     * @return array<string, mixed>
     */
    public function answers(): array
    {
        $data = $this->validated();
        $int = fn (string $key) => isset($data[$key]) ? (int) $data[$key] : null;

        return [
            'doctor_member_id' => $data['doctor_member_id'],
            'address' => $data['address'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'chief_complaint' => $data['chief_complaint'],
            'bad_previous_experience' => (bool) $data['bad_previous_experience'],
            'bad_experience_reason' => $data['bad_experience_reason'] ?? null,
            'uses_toothbrush' => (bool) $data['uses_toothbrush'],
            'brushings_per_day' => $int('brushings_per_day'),
            'extraction_complications' => $data['extraction_complications'] ?? [],
            'under_medical_treatment' => $data['under_medical_treatment'] ?? null,
            'conditions' => $data['conditions'] ?? [],
            'allergies' => $data['allergies'] ?? null,
            'blood_pressure' => $data['blood_pressure'] ?? null,
            'pulse' => $int('pulse'),
            'respiration' => $int('respiration'),
        ];
    }

    private function hasCondition(MedicalCondition $condition): bool
    {
        return in_array($condition->value, (array) $this->input('conditions', []), true);
    }
}
