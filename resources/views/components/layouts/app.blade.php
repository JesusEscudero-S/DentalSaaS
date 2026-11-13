@props(['title' => null, 'active' => '', 'breadcrumbs' => []])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/ui/logo-tooth.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50">
    @php
        $member = app(\App\Support\CurrentOrganization::class)->member();
        $userName = auth()->user()?->full_name ?? '';
        $active = $active ?? '';
        $nav = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'nav-dashboard', 'href' => null],
            ['key' => 'agenda', 'label' => 'Agenda', 'icon' => 'nav-agenda', 'href' => null],
            ['key' => 'pacientes', 'label' => 'Pacientes', 'icon' => 'nav-pacientes', 'href' => route('patients.index')],
            ['key' => 'recetas', 'label' => 'Recetas', 'icon' => 'nav-recetas', 'href' => null],
            ['key' => 'presupuestos', 'label' => 'Presupuestos', 'icon' => 'nav-presupuestos', 'href' => null],
            ['key' => 'facturacion', 'label' => 'Facturación', 'icon' => 'nav-facturacion', 'href' => null],
            ['key' => 'finanzas', 'label' => 'Finanzas', 'icon' => null, 'href' => null],
            ['key' => 'inventario', 'label' => 'Inventario', 'icon' => 'nav-inventario', 'href' => null],
        ];
    @endphp

    {{-- Sidebar (Figma: Sidebar, active=pacientes) --}}
    <aside class="fixed inset-y-0 left-0 z-30 flex w-sidebar flex-col border-r border-slate-200 bg-surface">
        <a href="{{ route('home') }}" class="flex h-topbar shrink-0 items-center gap-2.5 border-b border-slate-200 px-4">
            <span class="relative flex size-8 items-center justify-center rounded-lg bg-primary-600">
                <img src="{{ asset('img/ui/logo-tooth.svg') }}" alt="" width="30" height="30">
            </span>
            <span class="text-[12px] leading-8 text-slate-900">Dental <span class="text-primary-600">SaaS</span></span>
        </a>

        <nav class="flex flex-1 flex-col gap-1 overflow-y-auto px-2 pt-4" aria-label="Menú principal">
            <p class="px-0 pb-1 text-overline uppercase text-slate-400">Clínica</p>
            @foreach ($nav as $item)
                @php $isActive = $active === $item['key']; @endphp
                <a href="{{ $item['href'] ?? '#' }}"
                    @if (! $item['href']) aria-disabled="true" title="Disponible próximamente" @endif
                    @if ($isActive) aria-current="page" @endif
                    @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2 text-body-md font-bold transition-colors',
                        'bg-primary-50 text-primary-600' => $isActive,
                        'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $isActive && $item['href'],
                        'cursor-not-allowed text-slate-600' => ! $item['href'],
                    ])>
                    @if ($item['icon'])
                        <x-ui.icon :name="$item['icon']" :size="18" />
                    @else
                        <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="#33363F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" /><polyline points="17 6 23 6 23 12" />
                        </svg>
                    @endif
                    {{ $item['label'] }}
                </a>
            @endforeach

            <div class="my-1 h-px bg-slate-200"></div>
            <p class="pb-1 text-overline uppercase text-slate-400">Administración</p>
            <a href="#" aria-disabled="true" title="Disponible próximamente"
                class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-body-md font-bold text-slate-600">
                <x-ui.icon name="nav-configuracion" :size="18" />
                Configuración
            </a>
        </nav>

        <div class="flex h-[60px] shrink-0 items-center gap-2.5 border-t border-slate-200 px-3">
            <x-ui.avatar :initials="\App\Support\Ui::initials($userName)" size="sm" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-caption text-slate-900">{{ $userName }}</p>
                <p class="truncate text-[11px] text-slate-500">{{ $member?->role?->name }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded p-1 text-[11px] font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-900">Salir</button>
            </form>
        </div>
    </aside>

    <div class="pl-sidebar">
        {{-- TopBar --}}
        <header class="sticky top-0 z-20 flex h-topbar items-center justify-between border-b border-slate-200 bg-surface px-6">
            <nav class="flex items-center gap-1.5" aria-label="Ruta">
                <a href="{{ route('home') }}" class="text-body text-slate-500 hover:text-slate-900">Inicio</a>
                @foreach ($breadcrumbs ?? [] as $label => $url)
                    <x-ui.icon name="expand-right" :size="24" />
                    @if ($url && ! $loop->last)
                        <a href="{{ $url }}" class="text-body text-slate-500 hover:text-slate-900">{{ $label }}</a>
                    @else
                        <span class="text-body-md font-bold text-slate-900">{{ $label }}</span>
                    @endif
                @endforeach
            </nav>

            <div class="flex items-center gap-3">
                    <form method="GET" action="{{ route('patients.index') }}" role="search"
                        class="flex h-9 w-[272px] items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 focus-within:border-primary-600 focus-within:ring-2 focus-within:ring-primary-600/15">
                        <x-ui.icon name="search" :size="18" />
                        <input type="search" name="q" placeholder="Buscar paciente, cita, factura…" aria-label="Búsqueda global"
                            class="w-full border-0 bg-transparent p-0 text-body text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
                    </form>
                <button type="button" title="Tema oscuro (próximamente)" aria-label="Cambiar tema"
                    class="flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-surface hover:bg-slate-50">
                    <x-ui.icon name="moon" :size="24" />
                </button>
                <button type="button" title="Notificaciones" aria-label="Notificaciones"
                    class="flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-surface hover:bg-slate-50">
                    <x-ui.icon name="bell" :size="24" />
                </button>
                <x-ui.avatar :initials="mb_substr(\App\Support\Ui::initials($userName), 0, 1)" size="sm" />
            </div>
        </header>

        <main class="p-6">
            {{ $slot }}
        </main>
    </div>

    <x-ui.toast />
    @stack('modals')
</body>
</html>
