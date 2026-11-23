{{-- Finanzas › Categorías (RF-05) --}}
@php
    $tipoOptions = ['ingreso' => 'Ingresos', 'egreso' => 'Egresos'];
    $estadoOptions = ['todas' => 'Estado: todas', 'activas' => 'Estado: activas', 'inactivas' => 'Estado: inactivas'];
    $hasFilters = $filters['q'] !== '' || $filters['tipo'] !== '' || $filters['estado'] !== 'todas';
@endphp

<x-finances.shell tab="categories" section="Categorías" subtitle="Catálogo para clasificar ingresos y egresos">
    <x-slot:actions>
        <x-ui.button size="lg" x-data x-on:click="$dispatch('open-modal', { name: 'create-category' })">
            <x-ui.icon name="add" :size="16" />
            Nueva categoría
        </x-ui.button>
    </x-slot:actions>

    <x-ui.card class="overflow-hidden">
        <form method="GET" action="{{ route('finances.categories.index') }}" class="flex flex-wrap items-center gap-2.5 px-4 py-3.5" x-data>
            <label class="flex h-9 w-[300px] items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3.5 focus-within:border-primary-600 focus-within:ring-2 focus-within:ring-primary-600/15">
                <x-ui.icon name="search" :size="24" />
                <span class="sr-only">Buscar</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar categoría..."
                    class="h-full w-full border-0 bg-transparent px-2 text-body-md text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
            </label>
            <x-ui.select name="tipo" compact :options="$tipoOptions" placeholder="Todos los tipos" :value="$filters['tipo']"
                aria-label="Filtrar por tipo" x-on:change="$el.form.submit()" />
            <x-ui.select name="estado" compact :options="$estadoOptions" :value="$filters['estado']"
                aria-label="Filtrar por estado" x-on:change="$el.form.submit()" />
            @if ($hasFilters)
                <a href="{{ route('finances.categories.index') }}" class="text-body-md font-semibold text-primary-600 hover:underline">Limpiar filtros</a>
            @endif
        </form>

        <table class="w-full table-fixed border-collapse">
            <colgroup>
                <col class="w-[40%]"><col class="w-[15%]"><col class="w-[15%]"><col class="w-[15%]"><col class="w-[15%]">
            </colgroup>
            <thead>
                <tr class="border-y border-slate-200 bg-slate-50 text-left">
                    @foreach (['Nombre', 'Tipo', 'Movimientos', 'Estado', 'Acciones'] as $heading)
                        <th scope="col" class="px-4 py-[11px] text-[12px] font-semibold uppercase text-slate-400">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr class="group h-[53px] border-b border-slate-200 bg-white transition-colors hover:bg-slate-50 focus-within:bg-slate-50" x-data>
                        <td class="truncate px-4 py-3 text-body-md font-semibold text-slate-900">{{ $category->name }}</td>
                        <td class="px-4 py-3"><x-ui.badge :color="$category->direction->color()">{{ $category->direction->label() }}</x-ui.badge></td>
                        <td class="px-4 py-3 font-mono text-body text-slate-700">{{ $category->movements_count }}</td>
                        <td class="px-4 py-3"><x-ui.status-badge :status="$category->status->value" /></td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
                                <button type="button" title="Editar" aria-label="Editar {{ $category->name }}"
                                    class="shrink-0 rounded-md hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                    x-on:click="$dispatch('open-modal', { name: 'edit-category', category: @js(['id' => $category->id, 'name' => $category->name, 'direction' => $category->direction->value, 'locked' => $category->movements_count > 0]), action: @js(route('finances.categories.update', $category)) })">
                                    <x-ui.icon name="action-edit" :size="30" />
                                </button>
                                @if ($category->isActive())
                                    <button type="button" title="Desactivar" aria-label="Desactivar {{ $category->name }}"
                                        class="shrink-0 rounded-md hover:bg-danger-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500/50"
                                        x-on:click="$dispatch('open-modal', { name: 'deactivate-category', label: @js($category->name), action: @js(route('finances.categories.deactivate', $category)) })">
                                        <x-ui.icon name="action-delete" :size="30" />
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('finances.categories.reactivate', $category) }}">
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
                        <td colspan="5" class="px-4 py-16 text-center">
                            <p class="text-body font-semibold text-slate-900">
                                {{ $hasFilters ? 'No hay categorías que coincidan con los filtros' : 'Aún no hay categorías' }}
                            </p>
                            <p class="mt-1 text-body-md text-slate-500">
                                {{ $hasFilters ? 'Ajusta o limpia los filtros.' : 'Crea la primera categoría de ingresos o egresos.' }}
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="flex items-center justify-between gap-4 px-3 py-4">
            <p class="text-[12px] text-slate-600">
                @if ($categories->total())
                    Mostrando {{ $categories->firstItem() }}-{{ $categories->lastItem() }} de {{ $categories->total() }} {{ $categories->total() === 1 ? 'categoría' : 'categorías' }}
                @endif
            </p>
            {{ $categories->links() }}
        </div>
    </x-ui.card>

    @push('modals')
        @include('finances.partials.category-modals')
    @endpush
</x-finances.shell>
