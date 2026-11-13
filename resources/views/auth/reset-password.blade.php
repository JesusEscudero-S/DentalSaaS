<x-layouts.guest title="Nueva contraseña">
    <h1 class="mt-6 text-[18px] font-semibold leading-[26px] text-slate-900">Nueva contraseña</h1>
    <p class="mt-1 text-body-md text-slate-600">Usa al menos 8 caracteres.</p>

    <form method="POST" action="{{ route('password.store') }}" class="mt-6 flex flex-col gap-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-ui.input name="email" type="email" label="Correo electrónico" required :value="$request->email" autocomplete="username" />
        <x-ui.input name="password" type="password" label="Contraseña nueva" required autocomplete="new-password" />
        <x-ui.input name="password_confirmation" type="password" label="Confirma la contraseña" required autocomplete="new-password" />
        <x-ui.button type="submit" class="w-full">Guardar contraseña</x-ui.button>
    </form>
</x-layouts.guest>
