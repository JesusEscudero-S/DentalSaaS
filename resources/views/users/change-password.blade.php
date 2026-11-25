{{-- Módulo 1: cambiar la propia contraseña (RN-U6 obligatorio con contraseña temporal; P7 Mi perfil). --}}
<x-layouts.app title="Cambiar contraseña" :breadcrumbs="['Cambiar contraseña' => null]">
    <div class="mx-auto flex max-w-[480px] flex-col gap-5 pt-6">
        <div>
            <h1 class="text-h1 text-slate-900">Cambiar contraseña</h1>
            @if ($forced)
                <p class="pt-1 text-body text-slate-500">
                    Estás usando una contraseña temporal. Define una nueva para continuar.
                </p>
            @endif
        </div>

        <form method="POST" action="{{ route('password.change.update') }}" class="flex flex-col gap-4 rounded-[10px] border border-slate-200 bg-white p-6" novalidate>
            @csrf
            @method('PUT')
            <x-ui.input name="current_password" type="password" :label="$forced ? 'Contraseña temporal' : 'Contraseña actual'" required autocomplete="current-password" />
            <x-ui.input name="password" type="password" label="Nueva contraseña" required autocomplete="new-password"
                hint="Mínimo 8 caracteres, con mayúscula, minúscula y número." />
            <x-ui.input name="password_confirmation" type="password" label="Confirma la nueva contraseña" required autocomplete="new-password" />
            <div class="flex justify-end pt-2">
                <x-ui.button type="submit">Guardar contraseña</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.app>
