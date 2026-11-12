<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso de desarrollo · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/ui/logo-tooth.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 p-6">
    <x-ui.card class="w-full max-w-[520px] p-6 shadow-[0_12px_32px_rgba(15,23,42,0.08)]">
        <div class="flex items-center gap-2.5">
            <span class="flex size-8 items-center justify-center rounded-lg bg-primary-600">
                <img src="{{ asset('img/ui/logo-tooth.svg') }}" alt="" width="30" height="30">
            </span>
            <span class="text-[14px] text-slate-900">Dental <span class="text-primary-600">SaaS</span></span>
        </div>

        <h1 class="mt-5 text-[18px] font-semibold leading-[26px] text-slate-900">Acceso de desarrollo</h1>
        <p class="mt-1 text-body-md text-slate-600">
            Temporal hasta implementar el módulo de usuarios. Elige con qué miembro entrar; solo está disponible en entorno local.
        </p>

        <ul class="mt-5 divide-y divide-slate-200 border-y border-slate-200">
            @forelse ($members as $member)
                <li>
                    <form method="POST" action="{{ route('dev.login', $member->id) }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 px-2 py-3 text-left transition-colors hover:bg-slate-50 focus:outline-none focus-visible:bg-slate-50">
                            <x-ui.avatar :initials="\App\Support\Ui::initials($member->user->full_name)" :color="\App\Support\Ui::colorFor($member->id)" size="sm" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-body-md font-semibold text-slate-900">{{ $member->user->full_name }}</span>
                                <span class="block truncate text-[12px] text-slate-500">{{ $member->role?->name ?? 'Sin rol' }} · {{ $member->organization->commercial_name }}</span>
                            </span>
                            <x-ui.icon name="expand-right" :size="20" />
                        </button>
                    </form>
                </li>
            @empty
                <li class="py-6 text-center text-body-md text-slate-500">
                    No hay miembros. Ejecuta <code class="font-mono">php artisan db:seed</code>.
                </li>
            @endforelse
        </ul>
    </x-ui.card>
</body>
</html>
