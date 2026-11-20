<?php

namespace App\Http\Requests\Documents;

use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\Quote;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** Autorización de Tratamiento (parte4.html). Se usa también para la vista previa sin guardar. */
class StoreTreatmentAuthorizationRequest extends FormRequest
{
    protected $errorBag = 'treatmentAuthorization';

    private const ROWS = [
        'fluoride' => ['year', 'details'],
        'diagnoses' => ['date', 'text'],
        'activities' => ['date', 'description', 'doctor_member_id'],
    ];

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    /** Normaliza las filas dinámicas y descarta las que llegaron totalmente vacías. */
    protected function prepareForValidation(): void
    {
        $clean = ['quote_id' => $this->input('quote_id') ?: null];

        foreach (self::ROWS as $key => $fields) {
            $clean[$key] = collect((array) $this->input($key, []))
                ->filter(fn ($row) => is_array($row))
                ->map(fn (array $row) => collect($fields)->mapWithKeys(function (string $field) use ($row) {
                    $value = $row[$field] ?? null;

                    return [$field => is_string($value) && trim($value) !== '' ? preg_replace('/\s+/', ' ', trim($value)) : null];
                })->all())
                ->reject(fn (array $row) => collect($row)->filter()->isEmpty())
                ->values()
                ->all();
        }

        $this->merge($clean);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'uuid', function (string $attribute, mixed $value, Closure $fail) {
                if (! Patient::active()->whereKey($value)->exists()) {
                    $fail('El paciente no existe o está inactivo.');
                }
            }],
            'doctor_member_id' => ['required', 'uuid', $this->doctorRule()],
            'previous_document_id' => ['nullable', 'uuid'],

            'fluoride' => ['array', 'max:30'],
            'fluoride.*.year' => ['required', 'integer', 'between:1990,'.now()->year],
            'fluoride.*.details' => ['nullable', 'string', 'max:200'],

            'diagnoses' => ['array', 'max:30'],
            'diagnoses.*.text' => ['required', 'string', 'max:300'],
            'diagnoses.*.date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],

            'activities' => ['array', 'max:50'],
            'activities.*.description' => ['required', 'string', 'max:300'],
            'activities.*.date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'activities.*.doctor_member_id' => ['nullable', 'uuid', $this->doctorRule()],

            'quote_id' => ['nullable', 'uuid', function (string $attribute, mixed $value, Closure $fail) {
                $valid = Quote::where('patient_id', $this->input('patient_id'))
                    ->whereIn('status', ['enviado', 'aprobado'])
                    ->whereKey($value)
                    ->exists();
                if (! $valid) {
                    $fail('El presupuesto seleccionado no es válido para este paciente.');
                }
            }],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $years = 'El año debe estar entre 1990 y '.now()->year.'.';

        return [
            'fluoride.*.year.required' => 'Indica el año de la aplicación.',
            'fluoride.*.year.integer' => $years,
            'fluoride.*.year.between' => $years,
            'diagnoses.*.text.required' => 'Escribe el diagnóstico.',
            'activities.*.description.required' => 'Describe la actividad realizada.',
            'diagnoses.*.date.date_format' => 'La fecha no es válida.',
            'activities.*.date.date_format' => 'La fecha no es válida.',
            'diagnoses.*.date.before_or_equal' => 'La fecha no puede ser posterior a hoy.',
            'activities.*.date.before_or_equal' => 'La fecha no puede ser posterior a hoy.',
            'fluoride.max' => 'Se permiten hasta 30 aplicaciones de flúor.',
            'diagnoses.max' => 'Se permiten hasta 30 diagnósticos.',
            'activities.max' => 'Se permiten hasta 50 actividades.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'doctor_member_id' => 'doctor responsable',
            'fluoride.*.details' => 'detalle',
            'diagnoses.*.text' => 'diagnóstico',
            'activities.*.description' => 'actividad',
            'activities.*.doctor_member_id' => 'estomatólogo',
        ];
    }

    /**
     * Respuestas tipadas que se guardan en respuestas.json.
     *
     * @return array<string, mixed>
     */
    public function answers(): array
    {
        $data = $this->validated();

        return [
            'doctor_member_id' => $data['doctor_member_id'],
            'fluoride' => array_map(fn ($row) => ['year' => (int) $row['year'], 'details' => $row['details'] ?? null], $data['fluoride'] ?? []),
            'diagnoses' => array_map(fn ($row) => ['date' => $row['date'] ?? null, 'text' => $row['text']], $data['diagnoses'] ?? []),
            'activities' => array_map(fn ($row) => [
                'date' => $row['date'] ?? null,
                'description' => $row['description'],
                'doctor_member_id' => $row['doctor_member_id'] ?? null,
            ], $data['activities'] ?? []),
            'quote_id' => $data['quote_id'] ?? null,
        ];
    }

    private function doctorRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! OrganizationMember::doctors()->whereKey($value)->exists()) {
                $fail('El doctor seleccionado no es válido.');
            }
        };
    }
}
