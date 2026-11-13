<x-layouts.guest title="Recuperar contraseña">
    <h1 class="mt-6 text-[18px] font-semibold leading-[26px] text-slate-900">Recuperar contraseña</h1>
    <p class="mt-1 text-body-md text-slate-600">
        Escribe tu correo y te enviaremos un enlace para crear una contraseña nueva.
    </p>

    @if (session('status'))
        <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-body-md text-emerald-700" role="status">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 flex flex-col gap-4">
        @csrf
        <x-ui.input name="email" type="email" label="Correo electrónico" required autofocus placeholder="correo@ejemplo.com" />
        <x-ui.button type="submit" class="w-full">Enviar enlace</x-ui.button>
        <a href="{{ route('login') }}" class="text-center text-body-md font-semibold text-primary-600 hover:underline">Volver a iniciar sesión</a>
    </form>
</x-layouts.guest>
