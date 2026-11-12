{{-- Modales de contacto de emergencia (propuestos con el estilo de "Nuevo paciente"). --}}
@php
    $relationships = \App\Enums\GuardianRelationship::options();
    $editErrors = $errors->editGuardian->any();
@endphp

@can('pacientes.editar')
    <x-ui.modal name="create-guardian" title="Nuevo contacto" subtitle="Contacto de emergencia de {{ $patient->full_name }}"
        :show="$errors->createGuardian->any()" width="max-w-[480px]">
        <form method="POST" action="{{ route('patients.guardians.store', $patient) }}" class="flex flex-col gap-5" novalidate>
            @csrf
            <div class="flex flex-col gap-4">
                <x-ui.input name="full_name" label="Nombre completo" required bag="createGuardian" maxlength="250"
                    placeholder="Escribe el nombre completo" class="uppercase placeholder:normal-case" />
                <div class="grid grid-cols-2 gap-4">
                    <x-ui.select name="relationship" label="Parentesco" required bag="createGuardian" :options="$relationships" placeholder="Selecciona una opción" />
                    <x-ui.input name="phone" type="tel" label="Teléfono" required bag="createGuardian" placeholder="55 0000 0000" inputmode="numeric" maxlength="14" />
                </div>
                <x-ui.input name="email" type="email" label="Email" bag="createGuardian" placeholder="correo@ejemplo.com" maxlength="254" />
            </div>
            <x-ui.modal-footer />
        </form>
    </x-ui.modal>

    <x-ui.modal name="edit-guardian" title="Editar contacto" subtitle="Contacto de emergencia de {{ $patient->full_name }}"
        :show="$editErrors" width="max-w-[480px]">
        <form method="POST" :action="action" class="flex flex-col gap-5" novalidate
            x-data="{ form: @js($editErrors ? ['full_name' => old('full_name'), 'relationship' => old('relationship', ''), 'phone' => old('phone'), 'email' => old('email')] : (object) []), action: @js($editErrors ? old('_action', '') : '') }"
            x-on:open-modal.window="if ($event.detail.name === 'edit-guardian') { form = { ...$event.detail.guardian }; action = $event.detail.action }">
            @csrf
            @method('PUT')
            <input type="hidden" name="_action" :value="action">
            <div class="flex flex-col gap-4">
                <x-ui.input name="full_name" label="Nombre completo" required bag="editGuardian" maxlength="250" x-model="form.full_name" class="uppercase" />
                <div class="grid grid-cols-2 gap-4">
                    <x-ui.select name="relationship" label="Parentesco" required bag="editGuardian" :options="$relationships" placeholder="Selecciona una opción" x-model="form.relationship" />
                    <x-ui.input name="phone" type="tel" label="Teléfono" required bag="editGuardian" inputmode="numeric" maxlength="14" x-model="form.phone" />
                </div>
                <x-ui.input name="email" type="email" label="Email" bag="editGuardian" maxlength="254" x-model="form.email" />
            </div>
            <x-ui.modal-footer />
        </form>
    </x-ui.modal>

    <x-ui.modal name="delete-guardian" title="¿Borrar contacto?" width="max-w-[420px]">
        <p class="text-body text-slate-600">
            <span class="font-semibold text-slate-900" x-text="payload.label"></span>
            se quitará de los contactos de emergencia de este paciente.
        </p>
        <form method="POST" :action="payload.action">
            @csrf
            @method('DELETE')
            <x-ui.modal-footer type="danger" submit="Borrar" />
        </form>
    </x-ui.modal>
@endcan
