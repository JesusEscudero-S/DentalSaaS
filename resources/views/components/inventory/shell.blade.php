{{--
    Encabezado común de Inventario (Figma: Tarjeta_inventario + Notice).
    Título con totales, SegmentedControl Insumos · Movimientos, botones Entrada / Salida / Nuevo insumo
    y el aviso amarillo de insumos en o por debajo del stock mínimo (RN-I8).
--}}
@props(['tab', 'summary'])

@php
    use App\Support\Ui;

    $section = $tab === 'movements' ? 'Movimientos' : null;
    $segments = [
        'Insumos' => [route('inventory.index'), $tab === 'items'],
        'Movimientos' => [route('inventory.movements.index'), $tab === 'movements'],
    ];
    $count = $summary['count'];
@endphp

<x-layouts.app :title="$section ? $section.' · Inventario' : 'Inventario'" active="inventario"
    :breadcrumbs="$section ? ['Inventario' => route('inventory.index'), $section => null] : ['Inventario' => null]">
    <div class="flex items-start justify-between gap-6 pb-4">
        <div class="py-[3px]">
            <h1 class="text-h1 text-slate-900">Inventario</h1>
            <p class="pt-[3px] text-body font-semibold text-slate-400">
                {{ $count }} {{ $count === 1 ? 'insumo' : 'insumos' }} · valor {{ Ui::money($summary['value']) }}
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2.5 p-2.5" x-data>
            {{-- SegmentedControl de Figma --}}
            <nav class="flex rounded-lg bg-slate-100 p-[3px] text-[12.5px] font-semibold" aria-label="Secciones de inventario">
                @foreach ($segments as $text => [$href, $current])
                    <a href="{{ $href }}" @if ($current) aria-current="page" @endif
                        @class([
                            'rounded-md px-3.5 py-[5px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
                            'bg-white text-slate-900 shadow-sm' => $current,
                            'text-slate-700 hover:text-slate-900' => ! $current,
                        ])>{{ $text }}</a>
                @endforeach
            </nav>

            <x-ui.button variant="outline" size="sm" x-on:click="$dispatch('open-modal', { name: 'register-entry' })">
                <x-ui.icon name="arrow-down" :size="15" />
                Entrada
            </x-ui.button>
            <x-ui.button variant="outline" size="sm" x-on:click="$dispatch('open-modal', { name: 'register-exit' })">
                <x-ui.icon name="arrow-down" :size="15" class="rotate-180" />
                Salida
            </x-ui.button>
            <x-ui.button size="lg" x-on:click="$dispatch('open-modal', { name: 'create-item' })">
                <x-ui.icon name="add" :size="16" />
                Nuevo insumo
            </x-ui.button>
        </div>
    </div>

    @if ($summary['low'] > 0)
        {{-- Notice de Figma: semáforo de escasez (RN-I8) --}}
        <div class="mb-4 flex flex-wrap items-center gap-x-2 gap-y-1 rounded-[15px] border border-amber-200 bg-amber-50 px-4 py-2.5" role="status">
            <x-ui.icon name="alarm" :size="24" />
            <p class="text-[12px] font-semibold text-black">
                {{ $summary['low'] }} {{ $summary['low'] === 1 ? 'insumo en o por debajo' : 'insumos en o por debajo' }} del stock mínimo
            </p>
            <p class="text-[12px] font-light text-slate-700">· Considera generar una orden de compra</p>
            <a href="{{ route('inventory.index', ['stock' => 'bajo']) }}" class="ml-auto text-[12px] font-semibold text-primary-600 hover:underline">Ver insumos</a>
        </div>
    @endif

    {{ $slot }}
</x-layouts.app>
