{{--
    Modales del catálogo de inventario:
    - create-item: Figma "Overlay - Nuevo insumo" (+ Unidad, Lote y Caducidad que pide el PDF, RF 10.1).
    - edit-item:   mismo formulario sin stock inicial ni costo (el stock cambia con entradas y salidas).
    - deactivate-item: confirmación de baja lógica (RF 10.3, ModalFooter danger).
--}}
@php
    use App\Enums\InventoryCategory;
    use App\Enums\UnitOfMeasure;

    $editErrors = $errors->editItem->any();
    $editInitial = [
        'id' => $editErrors ? old('_id', '') : '',
        'name' => $editErrors ? old('name', '') : '',
        'sku' => $editErrors ? old('sku', '') : '',
        'category' => $editErrors ? old('category', '') : '',
        'unit_of_measure' => $editErrors ? old('unit_of_measure', '') : '',
        'supplier_name' => $editErrors ? old('supplier_name', '') : '',
        'min_quantity' => $editErrors ? old('min_quantity', '') : '',
        'location' => $editErrors ? old('location', '') : '',
        'stock' => $editErrors ? old('_stock', '') : '',
    ];
    $editAction = $editErrors && old('_id') ? route('inventory.update', old('_id')) : '';
@endphp

<datalist id="inventory-suppliers">
    @foreach ($supplierNames as $supplierName)
        <option value="{{ $supplierName }}"></option>
    @endforeach
</datalist>

<x-ui.modal name="create-item" title="Nuevo insumo" subtitle="Alta de insumo en inventario"
    :show="$errors->createItem->any()" width="max-w-[800px]">
    <form method="POST" action="{{ route('inventory.store') }}" class="flex flex-col gap-6" novalidate>
        @csrf
        <div class="grid grid-cols-2 gap-x-6 gap-y-4">
            <x-ui.input name="name" label="Nombre" required bag="createItem" maxlength="200" placeholder="Ej. Guantes nitrilo (M)" />
            <x-ui.input name="sku" label="SKU" required bag="createItem" maxlength="60" placeholder="Ej. GNT-NIT-M" class="uppercase" />
            <x-ui.select name="category" label="Categoría" required bag="createItem" placeholder="Selecciona la categoría"
                :options="InventoryCategory::options()" />
            <x-ui.input name="supplier_name" label="Proveedor" bag="createItem" maxlength="200" placeholder="Ej. MediSupply"
                list="inventory-suppliers" autocomplete="off" />
        </div>

        <div class="grid grid-cols-3 gap-x-6 gap-y-4">
            <x-ui.input name="initial_quantity" type="number" label="Stock inicial" required bag="createItem" value="0"
                min="0" step="1" inputmode="numeric" />
            <x-ui.input name="min_quantity" type="number" label="Stock mínimo" required bag="createItem" value="0"
                min="0" step="1" inputmode="numeric" />
            <x-ui.input name="unit_cost" type="number" label="Costo unitario" bag="createItem" placeholder="$0.00"
                min="0" step="0.01" inputmode="decimal" />
        </div>

        <div class="grid grid-cols-2 gap-x-6 gap-y-4">
            <x-ui.select name="unit_of_measure" label="Unidad" required bag="createItem" placeholder="Selecciona la unidad"
                :options="UnitOfMeasure::options()" />
            <x-ui.input name="location" label="Ubicación" bag="createItem" maxlength="100" placeholder="Ej. Estante A-1" />
            <x-ui.input name="lot_code" label="Lote" bag="createItem" maxlength="60" placeholder="Ej. L-2611" class="uppercase"
                hint="Opcional. Solo si registras stock inicial." />
            <x-ui.input name="expires_on" type="date" label="Fecha de caducidad" bag="createItem" min="{{ today()->toDateString() }}"
                hint="Opcional. Requiere el lote." />
        </div>

        <x-ui.modal-footer submit="Guardar insumo" />
    </form>
</x-ui.modal>

<x-ui.modal name="edit-item" title="Editar insumo" :show="$editErrors" width="max-w-[800px]">
    <form method="POST" :action="action" class="flex flex-col gap-6" novalidate
        x-data="{ form: @js($editInitial), action: @js($editAction) }"
        x-on:open-modal.window="if ($event.detail.name === 'edit-item') { form = { ...$event.detail.item }; action = $event.detail.item.updateUrl }">
        @csrf
        @method('PUT')
        <input type="hidden" name="_id" x-model="form.id">
        <input type="hidden" name="_stock" x-model="form.stock">

        <p class="-mt-3 text-body-md text-slate-600">
            Stock actual: <span class="font-semibold text-slate-900" x-text="form.stock"></span> · cambia con entradas y salidas.
        </p>

        <div class="grid grid-cols-2 gap-x-6 gap-y-4">
            <x-ui.input name="name" label="Nombre" required bag="editItem" maxlength="200" x-model="form.name" />
            <x-ui.input name="sku" label="SKU" required bag="editItem" maxlength="60" class="uppercase" x-model="form.sku" />
            <x-ui.select name="category" label="Categoría" required bag="editItem" placeholder="Selecciona la categoría"
                :options="InventoryCategory::options()" x-model="form.category" />
            <x-ui.input name="supplier_name" label="Proveedor" bag="editItem" maxlength="200" list="inventory-suppliers"
                autocomplete="off" x-model="form.supplier_name" />
            <x-ui.select name="unit_of_measure" label="Unidad" required bag="editItem" placeholder="Selecciona la unidad"
                :options="UnitOfMeasure::options()" x-model="form.unit_of_measure" />
            <x-ui.input name="min_quantity" type="number" label="Stock mínimo" required bag="editItem" min="0" step="1"
                inputmode="numeric" x-model="form.min_quantity" />
            <x-ui.input name="location" label="Ubicación" bag="editItem" maxlength="100" x-model="form.location" />
        </div>

        <x-ui.modal-footer submit="Guardar cambios" />
    </form>
</x-ui.modal>

<x-ui.modal name="deactivate-item" title="¿Dar de baja el material?" width="max-w-[420px]">
    <p class="text-body text-slate-600">
        <span class="font-semibold text-slate-900" x-text="payload.label"></span>
        ya no aparecerá al registrar entradas o salidas ni generará alertas de stock. Su historial se conserva y podrás reactivarlo después.
    </p>
    <form method="POST" :action="payload.action">
        @csrf
        @method('PATCH')
        <x-ui.modal-footer type="danger" submit="Dar de baja" />
    </form>
</x-ui.modal>
