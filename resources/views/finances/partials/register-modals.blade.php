{{--
    Modales de cajas: nueva, editar nombre, desactivar y abrir (RF-06, RF-07).
    Variables: $branches
--}}
@php
    $activeBranches = $branches->where('status', 'active');
    $mainBranchId = ($activeBranches->firstWhere('is_main', true) ?? $activeBranches->first())?->id;

    $editErrors = $errors->editRegister->any();
    $editInitial = [
        'id' => $editErrors ? old('_id', '') : '',
        'name' => $editErrors ? old('name', '') : '',
        'branch' => $editErrors ? old('_branch', '') : '',
    ];
    $editAction = $editErrors && old('_id') ? route('finances.cash.registers.update', old('_id')) : '';

    $openErrors = $errors->openSession->any();
    $openAction = $openErrors && old('_register_id') ? route('finances.cash.open', old('_register_id')) : '';
@endphp

<x-ui.modal name="create-register" title="Nueva caja" subtitle="Cada sucursal puede tener varias cajas" :show="$errors->createRegister->any()" width="max-w-[460px]">
    <form method="POST" action="{{ route('finances.cash.registers.store') }}" class="flex flex-col gap-5" novalidate>
        @csrf
        <div class="flex flex-col gap-4">
            <x-ui.input name="name" label="Nombre" required bag="createRegister" maxlength="100" placeholder="Ej. Caja recepción" />
            <x-ui.select name="branch_id" label="Sucursal" required bag="createRegister"
                :options="$activeBranches->pluck('name', 'id')->all()" :value="$mainBranchId" />
        </div>
        <x-ui.modal-footer />
    </form>
</x-ui.modal>

<x-ui.modal name="edit-register" title="Editar caja" :show="$editErrors" width="max-w-[460px]">
    <x-slot:sub><span x-text="payload.register?.branch ?? @js($editInitial['branch'])"></span></x-slot:sub>
    <form method="POST" :action="action" class="flex flex-col gap-5" novalidate
        x-data="{ form: @js($editInitial), action: @js($editAction) }"
        x-on:open-modal.window="if ($event.detail.name === 'edit-register') { form = { ...$event.detail.register }; action = $event.detail.action }">
        @csrf
        @method('PUT')
        <input type="hidden" name="_id" x-model="form.id">
        <input type="hidden" name="_branch" x-model="form.branch">
        <x-ui.input name="name" label="Nombre" required bag="editRegister" maxlength="100" x-model="form.name" />
        <x-ui.modal-footer />
    </form>
</x-ui.modal>

<x-ui.modal name="deactivate-register" title="¿Desactivar caja?" width="max-w-[420px]">
    <p class="text-body text-slate-600">
        <span class="font-semibold text-slate-900" x-text="payload.label"></span>
        ya no se podrá abrir. Su historial de cortes se conserva y podrás reactivarla después.
    </p>
    <form method="POST" :action="payload.action">
        @csrf
        @method('PATCH')
        <x-ui.modal-footer type="danger" submit="Desactivar" />
    </form>
</x-ui.modal>

<x-ui.modal name="open-session" title="Abrir caja" :show="$openErrors" width="max-w-[420px]">
    <x-slot:sub><span x-text="payload.label ?? @js(old('_label', ''))"></span></x-slot:sub>
    <form method="POST" :action="action" class="flex flex-col gap-5" novalidate
        x-data="{ action: @js($openAction), id: @js(old('_register_id', '')), label: @js(old('_label', '')) }"
        x-on:open-modal.window="if ($event.detail.name === 'open-session') { action = $event.detail.action; id = $event.detail.id; label = $event.detail.label }">
        @csrf
        <input type="hidden" name="_register_id" x-model="id">
        <input type="hidden" name="_label" x-model="label">
        <x-ui.input name="opening_amount" label="Monto de apertura" required bag="openSession" inputmode="decimal" placeholder="0.00" autocomplete="off"
            hint="Efectivo con el que inicia la caja (fondo para cambio)." />
        <x-ui.modal-footer submit="Abrir caja" />
    </form>
</x-ui.modal>
