<?php

namespace App\Http\Requests\Treatments;

use App\Enums\Dentition;
use App\Models\OrganizationMember;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Módulo 7: alta y modificación del plan (diagrama 1 pasos 4-6 y diagrama 3).
 * Validaciones del documento: al menos un procedimiento, servicio del catálogo (RN-T3),
 * pieza válida (11-48 o 51-85) y costo >= 0.
 */
class TreatmentPlanRequest extends FormRequest
{
    protected $errorBag = 'treatmentPlan';

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2). RN-T1 se aplicará ahí.
    }

    protected function prepareForValidation(): void
    {
        $procedures = collect($this->input('procedures', []))
            ->filter(fn ($row) => is_array($row) && filled($row['treatment_id'] ?? null))
            ->map(fn ($row) => [
                'treatment_id' => $row['treatment_id'],
                'tooth_fdi' => filled($row['tooth_fdi'] ?? null) ? (int) $row['tooth_fdi'] : null,
                'unit_price' => is_string($row['unit_price'] ?? null) ? str_replace([',', '$', ' '], '', $row['unit_price']) : ($row['unit_price'] ?? null),
                'notes' => filled($row['notes'] ?? null) ? trim($row['notes']) : null,
            ])
            ->values()
            ->all();

        $this->merge([
            'procedures' => $procedures,
            'diagnosis' => is_string($this->input('diagnosis')) ? trim($this->input('diagnosis')) : null,
            'change_reason' => filled($this->input('change_reason')) ? trim($this->input('change_reason')) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();

        return [
            'doctor_member_id' => ['required', 'uuid', function (string $attribute, mixed $value, Closure $fail) {
                if (! OrganizationMember::doctors()->whereKey($value)->exists()) {
                    $fail('El odontólogo seleccionado no es válido.');
                }
            }],
            'diagnosis' => ['required', 'string', 'max:2000'],
            'diagnosed_on' => ['required', 'date', 'before_or_equal:today'],
            'procedures' => ['required', 'array', 'min:1', 'max:40'],
            'procedures.*.treatment_id' => [
                'required', 'uuid',
                Rule::exists('treatments', 'id')->where('organization_id', $organizationId)->where('status', 'active'),
            ],
            'procedures.*.tooth_fdi' => ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) {
                if (Dentition::forTooth((int) $value) === null) {
                    $fail('La pieza debe ser válida (11 a 48, o 51 a 85 en dentición temporal).');
                }
            }],
            'procedures.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'procedures.*.notes' => ['nullable', 'string', 'max:120'],
            'change_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'procedures.required' => 'Agrega al menos un procedimiento.',
            'procedures.min' => 'Agrega al menos un procedimiento.',
            'procedures.*.treatment_id.exists' => 'El servicio no existe en el catálogo.',
            'procedures.*.treatment_id.required' => 'Selecciona el servicio.',
            'procedures.*.unit_price.min' => 'El costo debe ser mayor o igual a 0.',
            'procedures.*.unit_price.required' => 'Captura el costo.',
            'procedures.*.unit_price.numeric' => 'El costo debe ser un número.',
            'diagnosed_on.before_or_equal' => 'La fecha del diagnóstico no puede ser futura.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'doctor_member_id' => 'odontólogo responsable',
            'diagnosis' => 'diagnóstico dental',
            'diagnosed_on' => 'fecha del diagnóstico',
            'change_reason' => 'motivo del cambio',
        ];
    }
}
