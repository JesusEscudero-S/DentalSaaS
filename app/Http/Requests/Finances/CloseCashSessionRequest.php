<?php

namespace App\Http\Requests\Finances;

use App\Rules\MoneyAmount;
use Illuminate\Foundation\Http\FormRequest;

/**
 * RF-08: corte de caja. Que las notas sean obligatorias cuando hay diferencia lo decide
 * CashSessionService::close(), porque el esperado se calcula en el servidor.
 */
class CloseCashSessionRequest extends FormRequest
{
    protected $errorBag = 'closeSession';

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');

        $this->merge([
            'counted_cash' => MoneyAmount::normalize($this->input('counted_cash')),
            'notes' => is_string($notes) && trim($notes) !== '' ? trim($notes) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'counted_cash' => ['required', new MoneyAmount(allowZero: true)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['counted_cash' => 'efectivo contado', 'notes' => 'notas'];
    }
}
