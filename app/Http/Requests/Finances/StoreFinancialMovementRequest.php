<?php

namespace App\Http\Requests\Finances;

use App\Enums\MovementDirection;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\FinancialCategory;
use App\Rules\MoneyAmount;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** RF-01 / RF-02: alta de ingreso o egreso. */
class StoreFinancialMovementRequest extends FormRequest
{
    protected $errorBag = 'createMovement';

    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    protected function prepareForValidation(): void
    {
        $concept = $this->input('concept');
        $isCash = $this->input('method') === PaymentMethod::Cash->value;

        $this->merge([
            'concept' => is_string($concept) ? preg_replace('/\s+/', ' ', trim($concept)) : $concept,
            'amount' => MoneyAmount::normalize($this->input('amount')),
            'cash_session_id' => $isCash ? ($this->input('cash_session_id') ?: null) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(MovementDirection::class)],
            'category_id' => ['required', 'uuid', $this->categoryRule()],
            'concept' => ['required', 'string', 'min:3', 'max:300'],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.today()->subYear()->toDateString()],
            'amount' => ['required', new MoneyAmount],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'branch_id' => [
                'required', 'uuid',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! Branch::whereKey($value)->where('status', 'active')->exists()) {
                        $fail('La sucursal seleccionada no es válida.');
                    }
                },
            ],
            'cash_session_id' => ['nullable', 'uuid'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    /** RF-02: el efectivo se liga a una caja abierta de la misma sucursal. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $errors = $validator->errors();
            if ($this->input('method') !== PaymentMethod::Cash->value || $errors->hasAny(['method', 'branch_id', 'cash_session_id'])) {
                return;
            }

            $openInBranch = CashSession::open()->whereHas('register', fn ($q) => $q->where('branch_id', $this->input('branch_id')));

            if (! (clone $openInBranch)->exists()) {
                $errors->add('cash_session_id', 'Abre una caja en esta sucursal para registrar movimientos en efectivo');
            } elseif (! $this->input('cash_session_id')) {
                $errors->add('cash_session_id', 'Selecciona la caja donde entra o sale el efectivo.');
            } elseif (! $openInBranch->whereKey($this->input('cash_session_id'))->exists()) {
                $errors->add('cash_session_id', 'La caja seleccionada no está abierta en esta sucursal.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'occurred_on.date_format' => 'La fecha no es válida.',
            'occurred_on.before_or_equal' => 'La fecha no puede ser posterior a hoy.',
            'occurred_on.after_or_equal' => 'La fecha no puede tener más de un año de antigüedad.',
            'receipt.file' => 'No se pudo subir el comprobante.',
            'receipt.uploaded' => 'No se pudo subir el comprobante.',
            'receipt.mimes' => 'El comprobante debe ser una imagen JPG o PNG, o un PDF.',
            'receipt.max' => 'El comprobante no debe pesar más de 5 MB.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'direction' => 'tipo',
            'category_id' => 'categoría',
            'concept' => 'concepto',
            'occurred_on' => 'fecha',
            'amount' => 'monto',
            'method' => 'método de pago',
            'branch_id' => 'sucursal',
            'cash_session_id' => 'caja',
            'receipt' => 'comprobante',
        ];
    }

    private function categoryRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $category = FinancialCategory::find($value);

            if (! $category) {
                $fail('La categoría seleccionada no es válida.');
            } elseif (! $category->isActive()) {
                $fail('La categoría seleccionada está inactiva.');
            } elseif ($category->direction->value !== $this->input('direction')) {
                $fail('La categoría no corresponde al tipo de movimiento.');
            }
        };
    }
}
