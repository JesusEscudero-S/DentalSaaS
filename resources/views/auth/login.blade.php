{{-- Login (Breeze), con los componentes de Figma. --}}
<x-layouts.guest title="Iniciar sesión">
    <h1 class="mt-6 text-[18px] font-semibold leading-[26px] text-slate-900">Iniciar sesión</h1>
    <p class="mt-1 text-body-md text-slate-600">Ingresa con tu correo y contraseña.</p>

    @if (session('status'))
        <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-body-md text-emerald-700" role="status">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 flex flex-col gap-4">
        @csrf
        <x-ui.input name="email" type="email" label="Correo electrónico" required autofocus autocomplete="username"
            placeholder="correo@ejemplo.com" />
        <x-ui.input name="password" type="password" label="Contraseña" required autocomplete="current-password" />

        <div class="flex items-center justify-between">
            <x-ui.checkbox name="remember" label="Recordarme" />
            <a href="{{ route('password.request') }}" class="text-body-md font-semibold text-primary-600 hover:underline">
                ¿Olvidaste tu contraseña?
            </a>
        </div>

        <x-ui.button type="submit" class="mt-2 w-full">Entrar</x-ui.button>
    </form>
</x-layouts.guest>
