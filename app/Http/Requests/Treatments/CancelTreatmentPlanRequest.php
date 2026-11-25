<?php

namespace App\Http\Requests\Treatments;

use Illuminate\Foundation\Http\FormRequest;

/** Módulo 7: el motivo es obligatorio al cancelar (también sirve para la nota de corrección de una sesión). */
class CancelTreatmentPlanRequest extends FormRequest
{
    protected $errorBag = 'cancelPlan';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['reason.required' => 'Escribe el motivo.'];
    }
}
