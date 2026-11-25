{{-- Módulo 1, P7 "Mi perfil": cada usuario cambia su propia contraseña. --}}
<x-settings.shell active="perfil" section="Mi perfil" title="Cambiar contraseña">
    <div class="flex max-w-[480px] flex-col gap-5">
        <div>
            <h2 class="text-[18px] font-semibold text-slate-900">Cambiar contraseña</h2>
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
</x-settings.shell>
