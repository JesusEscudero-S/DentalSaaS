<?php

namespace App\Http\Requests\Finances;

use Illuminate\Foundation\Http\FormRequest;

/** RF-03: motivo de la reversión. Las demás reglas las aplica FinancialMovementService::reverse(). */
class ReverseFinancialMovementRequest extends FormRequest
{
    protected $errorBag = 'reverseMovement';

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');

        $this->merge(['reason' => is_string($reason) ? preg_replace('/\s+/', ' ', trim($reason)) : $reason]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:250'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reason' => 'motivo'];
    }
}
