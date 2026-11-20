<?php

namespace App\Http\Requests\Finances;

use App\Rules\MoneyAmount;
use Illuminate\Foundation\Http\FormRequest;

/** RF-07: apertura de caja. */
class OpenCashSessionRequest extends FormRequest
{
    protected $errorBag = 'openSession';

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['opening_amount' => MoneyAmount::normalize($this->input('opening_amount'))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['opening_amount' => ['required', new MoneyAmount(allowZero: true)]];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['opening_amount' => 'monto de apertura'];
    }
}
