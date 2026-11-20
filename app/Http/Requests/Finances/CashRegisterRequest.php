<?php

namespace App\Http\Requests\Finances;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF-06: alta (nombre y sucursal) y edición (solo nombre) de caja. */
class CashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (módulo 2. Gestión de roles).
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->register() ? 'editRegister' : 'createRegister';

        $name = $this->input('name');
        $this->merge([
            'name' => is_string($name) ? preg_replace('/\s+/', ' ', trim($name)) : $name,
            'branch_id' => $this->register()?->branch_id ?? $this->input('branch_id'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'min:2', 'max:100',
                Rule::unique('cash_registers', 'name')
                    ->where('organization_id', app(CurrentOrganization::class)->id())
                    ->where('branch_id', (string) $this->input('branch_id'))
                    ->ignore($this->register()?->id),
            ],
            'branch_id' => [
                'required', 'uuid',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! $this->register() && ! Branch::whereKey($value)->where('status', 'active')->exists()) {
                        $fail('La sucursal seleccionada no es válida.');
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.unique' => 'Ya existe una caja con ese nombre en esta sucursal.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'branch_id' => 'sucursal'];
    }

    private function register(): ?CashRegister
    {
        return $this->route('register');
    }
}
