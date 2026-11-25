{{-- Inventario › Insumos (Figma: página Inventario, Tabla_inventario). RF 10.1 · 10.2 · 10.3 --}}
@php
    use App\Enums\InventoryCategory;
    use App\Models\InventoryItem;
    use App\Support\Ui;

    $estadoOptions = ['activos' => 'Estado: activos', 'inactivos' => 'Estado: inactivos', 'todos' => 'Estado: todos'];
    $stockOptions = ['bajo' => 'Stock bajo'];
    $hasFilters = $filters['q'] !== '' || $filters['categoria'] !== '' || $filters['estado'] !== 'activos' || $filters['stock'] !== '';
    $soon = today()->addDays(30);
@endphp

<x-inventory.shell tab="items" :summary="$summary">
    <x-ui.card class="overflow-hidden">
        <form method="GET" action="{{ route('inventory.index') }}" class="flex flex-wrap items-center gap-2.5 px-4 py-3.5" x-data>
            <label class="flex h-9 w-[300px] items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3.5 focus-within:border-primary-600 focus-within:ring-2 focus-within:ring-primary-600/15">
                <x-ui.icon name="search" :size="24" />
                <span class="sr-only">Buscar</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por nombre o SKU..."
                    class="h-full w-full border-0 bg-transparent px-2 text-body-md text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
            </label>
            <x-ui.select name="categoria" compact :options="InventoryCategory::options()" placeholder="Todas las categorías" :value="$filters['categoria']"
                aria-label="Filtrar por categoría" x-on:change="$el.form.submit()" />
            <x-ui.select name="stock" compact :options="$stockOptions" placeholder="Todo el stock" :value="$filters['stock']"
                aria-label="Filtrar por stock" x-on:change="$el.form.submit()" />
            <x-ui.select name="estado" compact :options="$estadoOptions" :value="$filters['estado']"
                aria-label="Filtrar por estado" x-on:change="$el.form.submit()" />
            @if ($hasFilters)
                <a href="{{ route('inventory.index') }}" class="text-body-md font-semibold text-primary-600 hover:underline">Limpiar filtros</a>
            @endif
        </form>

        <table class="w-full table-fixed border-collapse">
            <colgroup>
                <col class="w-[22%]"><col class="w-[12%]"><col class="w-[12%]"><col class="w-[13%]"><col class="w-[8%]"><col class="w-[10%]"><col class="w-[10%]"><col class="w-[13%]">
            </colgroup>
            <thead>
                <tr class="border-y border-slate-200 bg-slate-50 text-left text-[12px] font-semibold uppercase text-slate-400">
                    <th scope="col" class="px-4 py-[11px]">Insumo</th>
                    <th scope="col" class="px-4 py-[11px]">SKU</th>
                    <th scope="col" class="px-4 py-[11px]">Categoría</th>
                    <th scope="col" class="px-4 py-[11px] text-right">Stock</th>
                    <th scope="col" class="px-4 py-[11px] text-center">Mínimo</th>
                    <th scope="col" class="px-4 py-[11px] text-right">Costo unit.</th>
                    <th scope="col" class="px-4 py-[11px]">Ubicación</th>
                    <th scope="col" class="px-4 py-[11px]"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    @php
                        $level = $item->stockLevel();
                        $nextExpiry = $item->next_expiry ? \Illuminate\Support\Carbon::parse($item->next_expiry) : null;
                    @endphp
                    <tr x-data @class([
                        'group border-b border-slate-200 transition-colors focus-within:bg-slate-50',
                        'bg-danger-50' => $level === 'danger',
                        'bg-amber-50' => $level === 'warning',
                        'bg-white hover:bg-slate-50' => $level === null,
                    ])>
                        <td class="px-4 py-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <p @class(['truncate text-body-md font-semibold', 'text-slate-900' => $item->isActive(), 'text-slate-400' => ! $item->isActive()])>{{ $item->name }}</p>
                                @unless ($item->isActive())
                                    <x-ui.status-badge status="inactive" />
                                @endunless
                            </div>
                            <p class="truncate text-[12px] text-slate-400">{{ $item->supplier?->name ?? 'Sin proveedor' }}</p>
                        </td>
                        <td class="truncate px-4 py-3 font-mono text-body-md text-slate-700">{{ $item->sku }}</td>
                        <td class="px-4 py-3">
                            @if ($item->category)
                                <x-ui.badge color="slate">{{ $item->category }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div @class([
                                'flex items-center justify-end gap-1.5 font-mono text-body-md font-bold',
                                'text-danger-500' => $level === 'danger',
                                'text-amber-500' => $level === 'warning',
                                'text-slate-700' => $level === null,
                            ])>
                                <span>{{ $item->quantityLabel() }}</span>
                                @if ($level)
                                    <x-ui.icon :name="'bell-pin-'.$level" :size="18" title="En o por debajo del stock mínimo" />
                                @endif
                            </div>
                            @if ($nextExpiry)
                                <p @class(['text-[11px]', 'font-semibold text-danger-600' => $nextExpiry->lte($soon), 'text-slate-400' => $nextExpiry->gt($soon)])>
                                    {{ $nextExpiry->isPast() && ! $nextExpiry->isToday() ? 'Caducó' : 'Caduca' }} {{ Ui::date($nextExpiry) }}
                                </p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center font-mono text-body-md font-bold text-slate-400">{{ InventoryItem::formatQuantity($item->minQuantity()) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-body-md font-bold text-slate-900">{{ Ui::money($item->stock?->avg_unit_cost) }}</td>
                        <td class="truncate px-4 py-3 text-body-md font-semibold text-slate-700">{{ $item->stock?->location ?: '—' }}</td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-end gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
                                @if ($item->isActive())
                                    <button type="button" title="Registrar entrada" aria-label="Registrar entrada de {{ $item->name }}"
                                        class="flex size-[30px] shrink-0 items-center justify-center rounded-md hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                        x-on:click="$dispatch('open-modal', { name: 'register-entry', item: @js($item->id) })">
                                        <x-ui.icon name="arrow-down" :size="15" />
                                    </button>
                                    <button type="button" title="Registrar salida" aria-label="Registrar salida de {{ $item->name }}"
                                        class="flex size-[30px] shrink-0 items-center justify-center rounded-md hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                        x-on:click="$dispatch('open-modal', { name: 'register-exit', item: @js($item->id) })">
                                        <x-ui.icon name="arrow-down" :size="15" class="rotate-180" />
                                    </button>
                                @endif
                                <button type="button" title="Editar" aria-label="Editar {{ $item->name }}"
                                    class="shrink-0 rounded-md hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                    x-on:click="$dispatch('open-modal', { name: 'edit-item', item: @js($item->toDetailArray()) })">
                                    <x-ui.icon name="action-edit" :size="30" />
                                </button>
                                @if ($item->isActive())
                                    <button type="button" title="Dar de baja" aria-label="Dar de baja {{ $item->name }}"
                                        class="shrink-0 rounded-md hover:bg-danger-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500/50"
                                        x-on:click="$dispatch('open-modal', { name: 'deactivate-item', label: @js($item->name), action: @js(route('inventory.deactivate', $item)) })">
                                        <x-ui.icon name="action-delete" :size="30" />
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('inventory.reactivate', $item) }}">
                                        @csrf
                                        @method('PATCH')
                                        <x-ui.button type="submit" variant="ghost" size="sm" class="!px-2 text-primary-600">Reactivar</x-ui.button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <p class="text-body font-semibold text-slate-900">
                                {{ $hasFilters ? 'No hay insumos que coincidan con los filtros' : 'Aún no hay insumos en el inventario' }}
                            </p>
                            <p class="mt-1 text-body-md text-slate-500">
                                {{ $hasFilters ? 'Ajusta o limpia los filtros.' : 'Registra el primer insumo con "Nuevo insumo".' }}
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="flex items-center justify-between gap-4 px-3 py-4">
            <p class="text-[12px] text-slate-600">
                @if ($items->total())
                    Mostrando {{ $items->firstItem() }}-{{ $items->lastItem() }} de {{ $items->total() }} {{ $items->total() === 1 ? 'insumo' : 'insumos' }}
                @endif
            </p>
            {{ $items->links() }}
        </div>
    </x-ui.card>

    @push('modals')
        @include('inventory.partials.item-modals')
        @include('inventory.partials.movement-modals')
    @endpush
</x-inventory.shell>
