{{-- Modales de Categorías: nueva, editar y desactivar (RF-05). --}}
@php
    use App\Enums\MovementDirection;

    $editErrors = $errors->editCategory->any();
    $editInitial = [
        'id' => $editErrors ? old('_id', '') : '',
        'name' => $editErrors ? old('name', '') : '',
        'direction' => $editErrors ? old('direction', '') : '',
        'locked' => $editErrors && old('_locked') === '1',
    ];
    $editAction = $editErrors && old('_id') ? route('finances.categories.update', old('_id')) : '';
@endphp

<x-ui.modal name="create-category" title="Nueva categoría" subtitle="Clasifica los ingresos y egresos de la clínica"
    :show="$errors->createCategory->any()" width="max-w-[460px]">
    <form method="POST" action="{{ route('finances.categories.store') }}" class="flex flex-col gap-5" novalidate>
        @csrf
        <div class="flex flex-col gap-4">
            <x-ui.input name="name" label="Nombre" required bag="createCategory" maxlength="100" placeholder="Ej. Material dental" />
            <x-ui.select name="direction" label="Tipo" required bag="createCategory" placeholder="Selecciona el tipo"
                :options="MovementDirection::options()" />
        </div>
        <x-ui.modal-footer />
    </form>
</x-ui.modal>

<x-ui.modal name="edit-category" title="Editar categoría" :show="$editErrors" width="max-w-[460px]">
    <form method="POST" :action="action" class="flex flex-col gap-5" novalidate
        x-data="{ form: @js($editInitial), action: @js($editAction) }"
        x-on:open-modal.window="if ($event.detail.name === 'edit-category') { form = { ...$event.detail.category }; action = $event.detail.action }">
        @csrf
        @method('PUT')
        <input type="hidden" name="_id" x-model="form.id">
        <input type="hidden" name="_locked" :value="form.locked ? '1' : '0'">
        <div class="flex flex-col gap-4">
            <x-ui.input name="name" label="Nombre" required bag="editCategory" maxlength="100" x-model="form.name" />
            <div class="flex flex-col gap-2">
                <x-ui.select name="direction" label="Tipo" required bag="editCategory" :options="MovementDirection::options()"
                    x-model="form.direction" x-bind:disabled="form.locked" />
                {{-- Un select deshabilitado no se envía: el tipo bloqueado viaja en este campo oculto. --}}
                <input type="hidden" name="direction" :value="form.direction" :disabled="! form.locked">
                <p x-show="form.locked" class="text-xs text-slate-500">El tipo no se puede cambiar porque la categoría ya tiene movimientos.</p>
            </div>
        </div>
        <x-ui.modal-footer />
    </form>
</x-ui.modal>

<x-ui.modal name="deactivate-category" title="¿Desactivar categoría?" width="max-w-[420px]">
    <p class="text-body text-slate-600">
        <span class="font-semibold text-slate-900" x-text="payload.label"></span>
        ya no aparecerá al registrar movimientos. Sus movimientos se conservan y seguirá disponible en filtros y reportes; podrás reactivarla después.
    </p>
    <form method="POST" :action="payload.action">
        @csrf
        @method('PATCH')
        <x-ui.modal-footer type="danger" submit="Desactivar" />
    </form>
</x-ui.modal>
