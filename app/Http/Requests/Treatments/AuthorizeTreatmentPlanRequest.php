<?php

namespace App\Http\Requests\Treatments;

use App\Enums\ConsentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Módulo 7, diagrama 1 paso 11: quién autoriza y cómo firmó. */
class AuthorizeTreatmentPlanRequest extends FormRequest
{
    protected $errorBag = 'authorizePlan';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('accepted_by_name');
        $this->merge([
            'accepted_by_name' => is_string($name) && trim($name) !== '' ? mb_strtoupper(preg_replace('/\s+/', ' ', trim($name))) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'patient_agrees' => ['accepted'],
            'accepted_by_name' => ['required', 'string', 'max:250'],
            'method' => ['required', Rule::enum(ConsentMethod::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['patient_agrees.accepted' => 'Confirma que el paciente revisó y firmó la autorización.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['accepted_by_name' => 'nombre de quien autoriza', 'method' => 'método de firma'];
    }
}
