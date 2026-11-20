<?php

namespace App\Http\Requests\Finances;

use App\Rules\MoneyAmount;
use Illuminate\Foundation\Http\FormRequest;

/** RF-09: ajuste (con signo) a una caja ya cortada. */
class StoreCashAdjustmentRequest extends FormRequest
{
    protected $errorBag = 'createAdjustment';

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');

        $this->merge([
            'amount' => MoneyAmount::normalize($this->input('amount')),
            'reason' => is_string($reason) ? preg_replace('/\s+/', ' ', trim($reason)) : $reason,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', new MoneyAmount(signed: true)],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['amount' => 'monto', 'reason' => 'motivo'];
    }
}
