<?php

namespace App\Http\Requests\Users;

use App\Models\OrganizationMember;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas comunes de alta y modificación de usuario (diagramas 1 y 2).
 */
abstract class UserRequest extends FormRequest
{
    abstract protected function ignoredMember(): ?OrganizationMember;

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles). RN-U1 se aplicará ahí.
    }

    protected function prepareForValidation(): void
    {
        $upper = fn ($v) => is_string($v) && trim($v) !== '' ? mb_strtoupper(preg_replace('/\s+/', ' ', trim($v))) : null;

        $this->merge([
            'full_name' => $upper($this->input('full_name')),
            'email' => $this->filled('email') ? mb_strtolower(trim($this->input('email'))) : null,
            'phone' => preg_replace('/\D/', '', (string) $this->input('phone')) ?: null,
            'professional_license' => $this->filled('professional_license') ? trim($this->input('professional_license')) : null,
            'specialty' => $this->filled('specialty') ? trim($this->input('specialty')) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();

        return [
            'full_name' => ['required', 'string', 'max:200'],
            // RN-U2: el correo es único (users.email es único en toda la BD).
            'email' => ['required', 'email:rfc', 'max:254', Rule::unique('users', 'email')->ignore($this->ignoredMember()?->user_id)],
            'phone' => ['required', 'digits:10'],
            // RN-U3: cada usuario tiene un rol existente y activo de la clínica.
            'role_id' => [
                'required', 'uuid',
                Rule::exists('roles', 'id')->where('organization_id', $organizationId)->where('status', 'active'),
            ],
            'professional_license' => ['nullable', 'string', 'max:30'],
            'specialty' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'phone.digits' => 'El teléfono debe tener exactamente 10 dígitos numéricos.',
            'role_id.exists' => 'El rol seleccionado no existe.',
            'role_id.required' => 'Selecciona un rol.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'full_name' => 'nombre completo',
            'email' => 'correo',
            'phone' => 'teléfono',
            'role_id' => 'rol',
            'professional_license' => 'cédula profesional',
            'specialty' => 'especialidad',
        ];
    }
}
