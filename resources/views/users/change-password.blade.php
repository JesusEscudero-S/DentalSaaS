{{-- Módulo 1, P7 "Mi perfil": cada usuario cambia su propia contraseña. --}}
<x-layouts.app title="Cambiar contraseña" :breadcrumbs="['Cambiar contraseña' => null]">
    <div class="mx-auto flex max-w-[480px] flex-col gap-5 pt-6">
        <div>
            <h1 class="text-h1 text-slate-900">Cambiar contraseña</h1>
        </div>

        <form method="POST" action="{{ route('password.change.update') }}" class="flex flex-col gap-4 rounded-[10px] border border-slate-200 bg-white p-6" novalidate>
            @csrf
            @method('PUT')
            <x-ui.input name="current_password" type="password" label="Contraseña actual" required autocomplete="current-password" />
            <x-ui.input name="password" type="password" label="Nueva contraseña" required autocomplete="new-password"
                hint="Mínimo 8 caracteres, con mayúscula, minúscula y número." />
            <x-ui.input name="password_confirmation" type="password" label="Confirma la nueva contraseña" required autocomplete="new-password" />
            <div class="flex justify-end pt-2">
                <x-ui.button type="submit">Guardar contraseña</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.app>
