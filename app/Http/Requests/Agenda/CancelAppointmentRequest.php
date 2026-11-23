<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF 4.4 Cancelar cita. RN-C3: el motivo es obligatorio (se guarda en el historial).
 */
class CancelAppointmentRequest extends FormRequest
{
    protected $errorBag = 'cancelAppointment';

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Escribe el motivo de la cancelación.',
        ];
    }
}
