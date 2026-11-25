<?php

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryCategory;
use App\Enums\UnitOfMeasure;
use App\Models\InventoryItem;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF 10.1: alta de material (modal "Nuevo insumo") y edición ("Editar insumo").
 * Un error bag por modal para reabrir el correcto si falla la validación.
 */
class InventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Sin permisos por ahora (RN-I1 lo resolverá el módulo 2. Gestión de roles).
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->item() ? 'editItem' : 'createItem';

        $this->merge([
            'name' => $this->clean($this->input('name')),
            'sku' => is_string($sku = $this->input('sku')) ? mb_strtoupper(preg_replace('/\s+/', '', $sku)) : $sku,
            'supplier_name' => $this->clean($this->input('supplier_name')),
            'location' => $this->clean($this->input('location')),
            'lot_code' => is_string($lot = $this->input('lot_code')) ? mb_strtoupper(trim($lot)) : $lot,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();

        $rules = [
            // RN-I2: el nombre del material es único (la BD solo obliga a que el SKU lo sea).
            'name' => [
                'required', 'string', 'min:2', 'max:200',
                Rule::unique('inventory_items', 'name')->where('organization_id', $organizationId)->ignore($this->item()?->id),
            ],
            'sku' => [
                'required', 'string', 'max:60', 'regex:/^[A-Z0-9][A-Z0-9.\-_\/]*$/',
                Rule::unique('inventory_items', 'sku')->where('organization_id', $organizationId)->ignore($this->item()?->id),
            ],
            'category' => ['required', Rule::enum(InventoryCategory::class)],
            'unit_of_measure' => ['required', Rule::enum(UnitOfMeasure::class)],
            'supplier_name' => ['nullable', 'string', 'max:200'],
            // RN-I3: nivel mínimo y cantidad inicial son enteros ≥ 0.
            'min_quantity' => ['required', 'integer', 'min:0', 'max:999999'],
            'location' => ['nullable', 'string', 'max:100'],
        ];

        if (! $this->item()) {
            $rules += [
                'initial_quantity' => ['required', 'integer', 'min:0', 'max:999999'],
                'unit_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999'],
                // Lote y caducidad solo tienen sentido si entra stock.
                'lot_code' => ['exclude_if:initial_quantity,0', 'nullable', 'string', 'max:60', 'required_with:expires_on'],
                'expires_on' => ['exclude_if:initial_quantity,0', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $integer = 'Debe ser un número entero mayor o igual a 0.';

        return [
            'name.unique' => 'Ya existe un material con ese nombre',
            'sku.unique' => 'Ya existe un material con ese SKU',
            'sku.regex' => 'Usa solo letras, números, guiones, puntos o diagonales (sin espacios).',
            'min_quantity.integer' => $integer,
            'min_quantity.min' => $integer,
            'initial_quantity.integer' => $integer,
            'initial_quantity.min' => $integer,
            'unit_cost.decimal' => 'Usa máximo 2 decimales.',
            'lot_code.required_with' => 'Indica el lote al que corresponde la caducidad.',
            'expires_on.after_or_equal' => 'La fecha de caducidad no puede ser anterior a hoy.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'sku' => 'SKU',
            'category' => 'categoría',
            'unit_of_measure' => 'unidad',
            'supplier_name' => 'proveedor',
            'min_quantity' => 'stock mínimo',
            'location' => 'ubicación',
            'initial_quantity' => 'stock inicial',
            'unit_cost' => 'costo unitario',
            'lot_code' => 'lote',
            'expires_on' => 'fecha de caducidad',
        ];
    }

    private function item(): ?InventoryItem
    {
        return $this->route('item');
    }

    private function clean(mixed $value): mixed
    {
        return is_string($value) ? preg_replace('/\s+/', ' ', trim($value)) : $value;
    }
}
