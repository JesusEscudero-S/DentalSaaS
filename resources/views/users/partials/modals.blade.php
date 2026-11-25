{{--
    Overlays del módulo 1: Nuevo usuario, Editar usuario, Desactivar, Reactivar,
    Restablecer contraseña y la contraseña temporal (se muestra una sola vez).
    Variables: $roles
--}}
@php
    $editErrors = $errors->editUser->any();
    $editInitial = $editErrors
        ? collect(['id', 'full_name', 'email', 'phone', 'role_id', 'professional_license', 'specialty'])
            ->mapWithKeys(fn ($f) => [$f => old($f === 'id' ? '_id' : $f, '')])->all()
        : [];
    $editAction = $editErrors && old('_id') ? route('users.update', old('_id')) : '';
    $temp = session('temp_password');
@endphp

<x-ui.modal name="create-user" title="Nuevo usuario" subtitle="Se le asignará una contraseña temporal" :show="$errors->createUser->any()">
    <form method="POST" action="{{ route('users.store') }}" class="flex flex-col gap-5" novalidate x-data="{ role: @js(old('role_id', '')) }">
        @csrf
        <div class="flex flex-col gap-4">
            @include('users.partials.form-fields', ['bag' => 'createUser', 'edit' => false])
        </div>
        <x-ui.modal-footer submit="Guardar usuario" />
    </form>
</x-ui.modal>

<x-ui.modal name="edit-user" title="Editar usuario" :show="$editErrors">
    <form method="POST" :action="action" class="flex flex-col gap-5" novalidate
        x-data="{ form: @js((object) $editInitial), action: @js($editAction) }"
        x-on:open-modal.window="if ($event.detail.name === 'edit-user') { form = { ...$event.detail.user }; action = $event.detail.user.urls.update }">
        @csrf
        @method('PUT')
        <input type="hidden" name="_id" x-model="form.id">
        <div class="flex flex-col gap-4">
            @include('users.partials.form-fields', ['bag' => 'editUser', 'edit' => true])
        </div>
        <p class="text-[12px] text-slate-500">Si cambias el rol, aplica la próxima vez que el usuario entre.</p>
        <x-ui.modal-footer />
    </form>
</x-ui.modal>

<x-ui.modal name="deactivate-user" title="¿Desactivar usuario?" width="max-w-[420px]">
    <p class="text-body text-slate-600">
        <span class="font-semibold text-slate-900" x-text="payload.user?.label"></span>
        ya no podrá iniciar sesión y se cerrarán sus sesiones abiertas. Sus citas, tratamientos y recetas se conservan y podrás reactivarlo después.
    </p>
    <form method="POST" :action="payload.user?.urls.deactivate">
        @csrf
        @method('PATCH')
        <x-ui.modal-footer type="danger" submit="Desactivar" />
    </form>
</x-ui.modal>

<x-ui.modal name="reactivate-user" title="¿Reactivar usuario?" width="max-w-[420px]">
    <p class="text-body text-slate-600">
        <span class="font-semibold text-slate-900" x-text="payload.user?.label"></span>
        podrá volver a iniciar sesión con su contraseña actual.
    </p>
    <form method="POST" :action="payload.user?.urls.reactivate">
        @csrf
        @method('PATCH')
        <x-ui.modal-footer submit="Reactivar" />
    </form>
</x-ui.modal>

<x-ui.modal name="reset-password" title="¿Restablecer contraseña?" width="max-w-[420px]">
    <p class="text-body text-slate-600">
        Se generará una contraseña temporal para
        <span class="font-semibold text-slate-900" x-text="payload.user?.label"></span>.
        Se cerrarán sus sesiones abiertas y tendrá que cambiarla al entrar.
    </p>
    <form method="POST" :action="payload.user?.urls.reset">
        @csrf
        @method('PATCH')
        <x-ui.modal-footer type="danger" submit="Restablecer" />
    </form>
</x-ui.modal>

@if ($temp)
    <x-ui.modal name="temp-password" :title="$temp['title']" width="max-w-[440px]" :show="true">
        <div class="flex flex-col gap-4" x-data="{ copied: false }">
            <p class="text-body text-slate-600">
                Entrega esta contraseña temporal a <span class="font-semibold text-slate-900">{{ $temp['name'] }}</span>.
                Tendrá que cambiarla la primera vez que entre. <span class="font-semibold">No se volverá a mostrar.</span>
            </p>
            <div class="flex flex-col gap-1 rounded-lg border border-slate-200 bg-slate-50 p-4">
                <span class="text-overline uppercase text-slate-400">Usuario</span>
                <span class="text-body-md font-semibold text-slate-900">{{ $temp['email'] }}</span>
                <span class="pt-2 text-overline uppercase text-slate-400">Contraseña temporal</span>
                <span class="font-mono text-[20px] font-bold tracking-wider text-slate-900" x-ref="pwd">{{ $temp['password'] }}</span>
            </div>
            <div class="flex items-center justify-end gap-3">
                <x-ui.button variant="secondary" x-on:click="navigator.clipboard.writeText($refs.pwd.textContent.trim()); copied = true">
                    <span x-text="copied ? 'Copiada' : 'Copiar'"></span>
                </x-ui.button>
                <x-ui.button x-on:click="open = false" data-autofocus>Listo</x-ui.button>
            </div>
        </div>
    </x-ui.modal>
@endif
