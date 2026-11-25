{{--
    Modales de movimientos (RF 10.2):
    - register-entry: Figma "Overlay - Registrar entrada" (+ costo, lote y caducidad opcionales).
    - register-exit:  misma forma; resta stock y muestra lo disponible (RN-I6).
    Se abren desde el encabezado (sin insumo) o desde una fila: $dispatch('open-modal', { name, item: id }).
--}}
@php
    use App\Models\InventoryItem;

    // [id => "Nombre (SKU) · 8 cartucho"] solo de materiales activos (RN-I10).
    $itemOptions = $activeItems->mapWithKeys(fn (InventoryItem $i) => [$i->id => "{$i->name} ({$i->sku}) · {$i->quantityLabel()}"])->all();
    $available = $activeItems->mapWithKeys(fn (InventoryItem $i) => [$i->id => $i->quantityLabel()])->all();

    $entryErrors = $errors->entryMovement->any();
    $exitErrors = $errors->exitMovement->any();
@endphp

<x-ui.modal name="register-entry" title="Registrar entrada" subtitle="Suma stock a un insumo" :show="$entryErrors" width="max-w-[600px]">
    <form method="POST" action="{{ route('inventory.entries.store') }}" class="flex flex-col gap-6" novalidate
        x-data="{ form: { item_id: @js($entryErrors ? old('item_id', '') : '') } }"
        x-on:open-modal.window="if ($event.detail.name === 'register-entry') form.item_id = $event.detail.item ?? ''">
        @csrf
        <div class="flex flex-col gap-4">
            <x-ui.select name="item_id" label="Insumo" required bag="entryMovement" placeholder="Selecciona un insumo"
                :options="$itemOptions" x-model="form.item_id" />
            <div class="grid grid-cols-2 gap-4">
                <x-ui.input name="quantity" type="number" label="Cantidad" required bag="entryMovement" placeholder="0"
                    min="1" step="1" inputmode="numeric" />
                <x-ui.input name="reference" label="Referencia" bag="entryMovement" maxlength="120" placeholder="Ej. Factura proveedor" />
            </div>
            <div class="grid grid-cols-3 gap-4">
                <x-ui.input name="unit_cost" type="number" label="Costo unitario" bag="entryMovement" placeholder="$0.00"
                    min="0" step="0.01" inputmode="decimal" />
                <x-ui.input name="lot_code" label="Lote" bag="entryMovement" maxlength="60" placeholder="Opcional" class="uppercase" />
                <x-ui.input name="expires_on" type="date" label="Caducidad" bag="entryMovement" min="{{ today()->toDateString() }}" />
            </div>
        </div>

        <x-ui.modal-footer submit="Registrar entrada" />
    </form>
</x-ui.modal>

<x-ui.modal name="register-exit" title="Registrar salida" subtitle="Descuenta stock por consumo de un insumo" :show="$exitErrors" width="max-w-[600px]">
    <form method="POST" action="{{ route('inventory.exits.store') }}" class="flex flex-col gap-6" novalidate
        x-data="{ form: { item_id: @js($exitErrors ? old('item_id', '') : '') }, available: @js($available) }"
        x-on:open-modal.window="if ($event.detail.name === 'register-exit') form.item_id = $event.detail.item ?? ''">
        @csrf
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <x-ui.select name="item_id" label="Insumo" required bag="exitMovement" placeholder="Selecciona un insumo"
                    :options="$itemOptions" x-model="form.item_id" />
                <p class="text-xs text-slate-500" x-show="available[form.item_id]" x-cloak>
                    Disponible: <span class="font-semibold text-slate-900" x-text="available[form.item_id]"></span>
                </p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-ui.input name="quantity" type="number" label="Cantidad" required bag="exitMovement" placeholder="0"
                    min="1" step="1" inputmode="numeric" />
                <x-ui.input name="reference" label="Referencia" bag="exitMovement" maxlength="120" placeholder="Ej. Consulta de Ana López" />
            </div>
        </div>

        <x-ui.modal-footer submit="Registrar salida" />
    </form>
</x-ui.modal>
