{{-- Inventario › Movimientos: historial de entradas y salidas (RF 10.2). Inmutable: se corrige con otro movimiento. --}}
@php
    use App\Enums\InventoryMovementType;
    use App\Models\InventoryItem;
    use App\Support\Ui;

    $hasFilters = $filters['q'] !== '' || $filters['tipo'] !== '';
@endphp

<x-inventory.shell tab="movements" :summary="$summary">
    <x-ui.card class="overflow-hidden">
        <form method="GET" action="{{ route('inventory.movements.index') }}" class="flex flex-wrap items-center gap-2.5 px-4 py-3.5" x-data>
            <label class="flex h-9 w-[300px] items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3.5 focus-within:border-primary-600 focus-within:ring-2 focus-within:ring-primary-600/15">
                <x-ui.icon name="search" :size="24" />
                <span class="sr-only">Buscar</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por nombre o SKU..."
                    class="h-full w-full border-0 bg-transparent px-2 text-body-md text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
            </label>
            <x-ui.select name="tipo" compact :options="InventoryMovementType::options()" placeholder="Todos los tipos" :value="$filters['tipo']"
                aria-label="Filtrar por tipo" x-on:change="$el.form.submit()" />
            @if ($hasFilters)
                <a href="{{ route('inventory.movements.index') }}" class="text-body-md font-semibold text-primary-600 hover:underline">Limpiar filtros</a>
            @endif
        </form>

        <table class="w-full table-fixed border-collapse">
            <colgroup>
                <col class="w-[14%]"><col class="w-[22%]"><col class="w-[12%]"><col class="w-[12%]"><col class="w-[10%]"><col class="w-[14%]"><col class="w-[16%]">
            </colgroup>
            <thead>
                <tr class="border-y border-slate-200 bg-slate-50 text-left text-[12px] font-semibold uppercase text-slate-400">
                    <th scope="col" class="px-4 py-[11px]">Fecha</th>
                    <th scope="col" class="px-4 py-[11px]">Insumo</th>
                    <th scope="col" class="px-4 py-[11px]">Tipo</th>
                    <th scope="col" class="px-4 py-[11px] text-right">Cantidad</th>
                    <th scope="col" class="px-4 py-[11px] text-right">Costo unit.</th>
                    <th scope="col" class="px-4 py-[11px]">Referencia</th>
                    <th scope="col" class="px-4 py-[11px]">Registró</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr class="border-b border-slate-200 bg-white transition-colors hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <p class="text-body-md text-slate-900">{{ Ui::date($movement->occurred_at) }}</p>
                            <p class="text-[12px] text-slate-400">{{ $movement->occurred_at->format('H:i') }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="truncate text-body-md font-semibold text-slate-900">{{ $movement->item?->name }}</p>
                            <p class="truncate font-mono text-[12px] text-slate-400">
                                {{ $movement->item?->sku }}@if ($movement->lot) · Lote {{ $movement->lot->lot_code }}@endif
                            </p>
                        </td>
                        <td class="px-4 py-3"><x-ui.badge :color="$movement->movement_type->color()">{{ $movement->movement_type->label() }}</x-ui.badge></td>
                        <td @class([
                            'px-4 py-3 text-right font-mono text-body-md font-bold',
                            'text-emerald-700' => $movement->isIncoming(),
                            'text-danger-600' => ! $movement->isIncoming(),
                        ])>
                            {{ $movement->isIncoming() ? '+' : '−' }}{{ InventoryItem::formatQuantity(ltrim((string) $movement->quantity, '-')) }}
                            <span class="font-sans font-normal text-slate-500">{{ $movement->item?->unit_of_measure }}</span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-body-md text-slate-700">
                            {{ $movement->unit_cost !== null ? Ui::money($movement->unit_cost) : '—' }}
                        </td>
                        <td class="truncate px-4 py-3 text-body-md text-slate-700" title="{{ $movement->reference }}">{{ $movement->reference ?: '—' }}</td>
                        <td class="truncate px-4 py-3 text-body-md text-slate-700">{{ $movement->actor?->full_name }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center">
                            <p class="text-body font-semibold text-slate-900">
                                {{ $hasFilters ? 'No hay movimientos que coincidan con los filtros' : 'Aún no hay movimientos' }}
                            </p>
                            <p class="mt-1 text-body-md text-slate-500">
                                {{ $hasFilters ? 'Ajusta o limpia los filtros.' : 'Las entradas y salidas de material aparecerán aquí.' }}
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="flex items-center justify-between gap-4 px-3 py-4">
            <p class="text-[12px] text-slate-600">
                @if ($movements->total())
                    Mostrando {{ $movements->firstItem() }}-{{ $movements->lastItem() }} de {{ $movements->total() }} {{ $movements->total() === 1 ? 'movimiento' : 'movimientos' }}
                @endif
            </p>
            {{ $movements->links() }}
        </div>
    </x-ui.card>

    @push('modals')
        @include('inventory.partials.item-modals')
        @include('inventory.partials.movement-modals')
    @endpush
</x-inventory.shell>
