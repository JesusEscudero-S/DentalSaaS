<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryItem;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF 10.2: registrar entrada o salida de material. La ruta decide el tipo
 * (inventory.entries.store / inventory.exits.store) y el error bag del modal.
 */
class InventoryMovementRequest extends FormRequest
{
    private ?InventoryItem $selectedItem = null;

    public function authorize(): bool
    {
        return true; // RN-I5: cualquier usuario con acceso a la clínica registra consumos.
    }

    public function isExit(): bool
    {
        return $this->routeIs('inventory.exits.store');
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->isExit() ? 'exitMovement' : 'entryMovement';

        $this->merge([
            'reference' => is_string($ref = $this->input('reference')) ? preg_replace('/\s+/', ' ', trim($ref)) : $ref,
            'lot_code' => is_string($lot = $this->input('lot_code')) ? mb_strtoupper(trim($lot)) : $lot,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'item_id' => [
                'required', 'uuid',
                Rule::exists('inventory_items', 'id')->where('organization_id', app(CurrentOrganization::class)->id()),
                // RN-I10: un material dado de baja no admite movimientos.
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($this->item()?->isActive() === false) {
                        $fail('El material está dado de baja; reactívalo para registrar movimientos.');
                    }
                },
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];

        if (! $this->isExit()) {
            $rules += [
                'unit_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999'],
                'lot_code' => ['nullable', 'string', 'max:60', 'required_with:expires_on'],
                'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $quantity = 'La cantidad debe ser un número entero mayor a 0.';

        return [
            'item_id.required' => 'Selecciona un insumo.',
            'item_id.uuid' => 'Selecciona un insumo de la lista.',
            'item_id.exists' => 'Selecciona un insumo de la lista.',
            'quantity.integer' => $quantity,
            'quantity.min' => $quantity,
            'unit_cost.decimal' => 'Usa máximo 2 decimales.',
            'lot_code.required_with' => 'Indica el lote al que corresponde la caducidad.',
            'expires_on.after_or_equal' => 'La fecha de caducidad no puede ser anterior a hoy.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'item_id' => 'insumo',
            'quantity' => 'cantidad',
            'reference' => 'referencia',
            'unit_cost' => 'costo unitario',
            'lot_code' => 'lote',
            'expires_on' => 'fecha de caducidad',
        ];
    }

    /** Insumo elegido (el global scope impide tomar uno de otra clínica). */
    public function item(): ?InventoryItem
    {
        $id = $this->input('item_id');

        if (! is_string($id) || $id === '') {
            return null;
        }

        return $this->selectedItem ??= InventoryItem::find($id);
    }
}
