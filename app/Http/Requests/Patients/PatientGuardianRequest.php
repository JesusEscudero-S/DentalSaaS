<?php

namespace App\Http\Requests\Patients;

use App\Enums\GuardianRelationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF 3.5 / 3.7: alta y modificación de contacto de emergencia. */
class PatientGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pacientes.editar');
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->route('guardian') ? 'editGuardian' : 'createGuardian';

        $name = $this->input('full_name');

        $this->merge([
            'full_name' => is_string($name) ? mb_strtoupper(preg_replace('/\s+/', ' ', trim($name))) : $name,
            'phone' => preg_replace('/\D/', '', (string) $this->input('phone')) ?: null,
            'email' => $this->filled('email') ? mb_strtolower(trim($this->input('email'))) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:250'],
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'phone' => ['required', 'digits:10'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.digits' => 'El teléfono debe tener exactamente 10 dígitos numéricos.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'full_name' => 'nombre completo',
            'relationship' => 'parentesco',
            'phone' => 'teléfono',
            'email' => 'email',
        ];
    }
}
