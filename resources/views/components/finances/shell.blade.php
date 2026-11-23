{{-- Encabezado y tabs comunes de Finanzas (Resumen · Movimientos · Caja · Categorías). --}}
@props(['tab', 'section', 'subtitle' => null, 'breadcrumbs' => null])

@php
    $tabs = array_map(fn ($t) => [...$t, 'active' => $t['key'] === $tab], [
        ['key' => 'summary', 'label' => 'Resumen', 'href' => route('finances.index')],
        ['key' => 'movements', 'label' => 'Movimientos', 'href' => route('finances.movements.index')],
        ['key' => 'cash', 'label' => 'Caja', 'href' => Route::has('finances.cash.index') ? route('finances.cash.index') : null],
        ['key' => 'categories', 'label' => 'Categorías', 'href' => route('finances.categories.index')],
    ]);
@endphp

<x-layouts.app :title="$section.' · Finanzas'" active="finanzas" :breadcrumbs="$breadcrumbs ?? ['Finanzas' => route('finances.index'), $section => null]">
    <div class="flex items-start justify-between gap-6 pb-4">
        <div class="py-[3px]">
            <h1 class="text-h1 text-slate-900">Finanzas</h1>
            @if ($subtitle)
                <p class="pt-[3px] text-body font-semibold text-slate-400">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex items-center gap-2.5 p-2.5">{{ $actions }}</div>
        @endisset
    </div>

    <x-ui.tabs :items="$tabs" class="mb-5" />

    {{ $slot }}
</x-layouts.app>
