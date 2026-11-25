{{--
    Shell de Configuración (Figma: "Configuración"): título, menú de secciones a la izquierda y contenido.
    Las secciones sin pantalla todavía se muestran deshabilitadas ("Disponible próximamente").

    Uso: <x-settings.shell active="usuarios" section="Usuarios y roles"> … </x-settings.shell>
--}}
@props(['active' => 'usuarios', 'section' => null, 'title' => null])

@php
    // [clave, etiqueta, ruta o null, ícono]
    $sections = [
        ['clinica', 'Clínica', null, 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-4h6v4M9 9h.01M15 9h.01M9 13h.01M15 13h.01'],
        ['usuarios', 'Usuarios y roles', route('users.index'), 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75'],
        ['tratamientos', 'Tratamientos', route('settings.treatments'), 'M4.8 2.3A.3.3 0 105 2H4a2 2 0 00-2 2v5a6 6 0 006 6 6 6 0 006-6V4a2 2 0 00-2-2h-1a.2.2 0 10.3.3M8 15v1a6 6 0 006 6 6 6 0 006-6v-4M22 10a2 2 0 11-4 0 2 2 0 014 0z'],
        ['facturacion', 'Facturación', null, 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8'],
        ['notificaciones', 'Notificaciones', null, 'M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0'],
        ['sucursales', 'Sucursales', null, 'M12 22s-8-4.5-8-11.8A8 8 0 0112 2a8 8 0 018 8.2c0 7.3-8 11.8-8 11.8zM12 13a3 3 0 100-6 3 3 0 000 6z'],
        ['perfil', 'Mi perfil', route('password.change'), 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z'],
    ];
    $breadcrumbs = $section ? ['Configuración' => route('settings.index'), $section => null] : ['Configuración' => null];
@endphp

<x-layouts.app :title="$title ?? $section ?? 'Configuración'" active="configuracion" :breadcrumbs="$breadcrumbs">
    <div class="flex gap-6">
        <aside class="w-[215px] shrink-0">
            <h1 class="text-h1 text-slate-900">Configuración</h1>
            <p class="pt-1 text-body-md text-slate-500">Administra tu clínica y preferencias</p>

            <nav class="mt-6 flex flex-col gap-1.5" aria-label="Secciones de configuración">
                @foreach ($sections as [$key, $label, $href, $icon])
                    @php $isActive = $key === $active; @endphp
                    <a href="{{ $href ?? '#' }}"
                        @if (! $href) aria-disabled="true" title="Disponible próximamente" @endif
                        @if ($isActive) aria-current="page" @endif
                        @class([
                            'flex items-center gap-3 rounded-lg px-3 py-2 text-body-md font-semibold transition-colors',
                            'bg-primary-50 text-primary-600' => $isActive,
                            'bg-white text-slate-700 hover:bg-slate-100 hover:text-slate-900' => ! $isActive && $href,
                            'cursor-not-allowed bg-white text-slate-400' => ! $href,
                        ])>
                        <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="{{ $icon }}" />
                        </svg>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="min-w-0 flex-1">
            {{ $slot }}
        </div>
    </div>
</x-layouts.app>
