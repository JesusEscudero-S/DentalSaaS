<?php

namespace App\Http\Requests\Treatments;

use App\Models\OrganizationMember;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** Módulo 7, diagrama 2: fecha (no futura), actividades y responsable son obligatorios. */
class TreatmentSessionRequest extends FormRequest
{
    protected $errorBag = 'treatmentSession';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'performed_on' => ['required', 'date', 'before_or_equal:today'],
            'activities' => ['required', 'string', 'max:2000'],
            'doctor_member_id' => ['required', 'uuid', function (string $attribute, mixed $value, Closure $fail) {
                if (! OrganizationMember::doctors()->whereKey($value)->exists()) {
                    $fail('El odontólogo seleccionado no es válido.');
                }
            }],
            'procedures' => ['nullable', 'array'],
            'procedures.*' => ['uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'performed_on.before_or_equal' => 'La fecha de la sesión no puede ser futura.',
            'activities.required' => 'Describe las actividades realizadas.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['performed_on' => 'fecha', 'activities' => 'actividades realizadas', 'doctor_member_id' => 'odontólogo responsable'];
    }
}
