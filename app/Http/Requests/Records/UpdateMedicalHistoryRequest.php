<?php

namespace App\Http\Requests\Records;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF 6.2 Actualizar antecedentes. Ambos campos pueden ir vacíos (NULLABLE en el PDF y en la BD).
 * El tipo de sangre del PDF no existe en la BD (la BD manda), por eso no está aquí.
 */
class UpdateMedicalHistoryRequest extends FormRequest
{
    protected $errorBag = 'medicalHistory';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'allergies' => ['nullable', 'string', 'max:2000'],
            'medical_history' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['allergies' => 'alergias', 'medical_history' => 'antecedentes y enfermedades crónicas'];
    }
}
