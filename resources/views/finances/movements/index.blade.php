{{-- Finanzas › Movimientos (RF-01 a RF-04) --}}
@php
    use App\Enums\MovementDirection;
    use App\Enums\PaymentMethod;
    use App\Support\Ui;

    $tipoOptions = ['ingreso' => 'Ingresos', 'egreso' => 'Egresos'];
    $categoryOptions = $categories->mapWithKeys(fn ($c) => [$c->id => $c->name.' · '.$c->direction->label().($c->isActive() ? '' : ' (inactiva)')])->all();
    $branchOptions = $branches->pluck('name', 'id')->all();
    $hasFilters = collect($filters)->except('revertidos')->contains(fn ($v) => $v !== '') || $filters['revertidos'];
    $netPositive = bccomp($totals['net'], '0', 2) >= 0;
    $dateInput = 'h-9 rounded-lg border border-slate-300 bg-surface px-3 text-[13.5px] text-slate-900 hover:border-slate-400 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/15';
@endphp

<x-finances.shell tab="movements" section="Movimientos" subtitle="Ingresos y egresos de la clínica">
    <x-slot:actions>
        <x-ui.button variant="outline" size="sm" :href="route('finances.movements.export', request()->query())">
            <x-ui.icon name="import" :size="24" />
            Exportar
        </x-ui.button>
        <x-ui.button variant="secondary" x-data x-on:click="$dispatch('open-modal', { name: 'create-movement', direction: 'egreso' })">
            Registrar egreso
        </x-ui.button>
        <x-ui.button size="lg" x-data x-on:click="$dispatch('open-modal', { name: 'create-movement', direction: 'ingreso' })">
            <x-ui.icon name="add" :size="16" />
            Registrar ingreso
        </x-ui.button>
    </x-slot:actions>

    {{-- Totales vigentes del filtro (RF-04) --}}
    <div class="mb-4 grid grid-cols-3 gap-4">
        <x-ui.card class="px-5 py-4">
            <p class="text-overline uppercase text-slate-400">Ingresos</p>
            <p class="mt-1 font-mono text-[20px] font-bold leading-7 text-emerald-600">+{{ Ui::money($totals['income']) }}</p>
        </x-ui.card>
        <x-ui.card class="px-5 py-4">
            <p class="text-overline uppercase text-slate-400">Egresos</p>
            <p class="mt-1 font-mono text-[20px] font-bold leading-7 text-danger-600">−{{ Ui::money($totals['expense']) }}</p>
        </x-ui.card>
        <x-ui.card class="px-5 py-4">
            <p class="text-overline uppercase text-slate-400">Neto</p>
            <p @class(['mt-1 font-mono text-[20px] font-bold leading-7', 'text-slate-900' => $netPositive, 'text-danger-600' => ! $netPositive])>
                {{ $netPositive ? '' : '−' }}{{ Ui::money(ltrim($totals['net'], '-')) }}
            </p>
        </x-ui.card>
    </div>

    <x-ui.card class="overflow-hidden">
        {{-- Búsqueda y filtros --}}
        <form method="GET" action="{{ route('finances.movements.index') }}" class="flex flex-wrap items-center gap-2.5 px-4 py-3.5" x-data>
            <label class="flex h-9 w-[260px] items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3.5 focus-within:border-primary-600 focus-within:ring-2 focus-within:ring-primary-600/15">
                <x-ui.icon name="search" :size="24" />
                <span class="sr-only">Buscar</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por concepto..."
                    class="h-full w-full border-0 bg-transparent px-2 text-body-md text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
            </label>
            <x-ui.select name="tipo" compact :options="$tipoOptions" placeholder="Todos los tipos" :value="$filters['tipo']"
                aria-label="Filtrar por tipo" x-on:change="$el.form.submit()" />
            <x-ui.select name="categoria" compact :options="$categoryOptions" placeholder="Todas las categorías" :value="$filters['categoria']"
                aria-label="Filtrar por categoría" x-on:change="$el.form.submit()" />
            <x-ui.select name="metodo" compact :options="PaymentMethod::options()" placeholder="Todos los métodos" :value="$filters['metodo']"
                aria-label="Filtrar por método" x-on:change="$el.form.submit()" />
            @if (count($branchOptions) > 1)
                <x-ui.select name="sucursal" compact :options="$branchOptions" placeholder="Todas las sucursales" :value="$filters['sucursal']"
                    aria-label="Filtrar por sucursal" x-on:change="$el.form.submit()" />
            @endif
            <label class="flex items-center gap-1.5 text-body-md text-slate-500">
                Desde
                <input type="date" name="desde" value="{{ $filters['desde'] }}" class="{{ $dateInput }}" x-on:change="$el.form.submit()">
            </label>
            <label class="flex items-center gap-1.5 text-body-md text-slate-500">
                Hasta
                <input type="date" name="hasta" value="{{ $filters['hasta'] }}" class="{{ $dateInput }}" x-on:change="$el.form.submit()">
            </label>
            <x-ui.checkbox name="revertidos" label="Incluir revertidos" :checked="$filters['revertidos']" x-on:change="$el.form.submit()" />
            @if ($hasFilters)
                <a href="{{ route('finances.movements.index') }}" class="text-body-md font-semibold text-primary-600 hover:underline">Limpiar filtros</a>
            @endif
        </form>

        <table class="w-full table-fixed border-collapse">
            <colgroup>
                <col class="w-[10%]"><col class="w-[22%]"><col class="w-[12%]"><col class="w-[10%]"><col class="w-[10%]">
                <col class="w-[12%]"><col class="w-[12%]"><col class="w-[4%]"><col class="w-[8%]">
            </colgroup>
            <thead>
                <tr class="border-y border-slate-200 bg-slate-50 text-left">
                    @foreach (['Fecha', 'Concepto', 'Categoría', 'Sucursal', 'Método', 'Registrado por'] as $heading)
                        <th scope="col" class="px-4 py-[11px] text-[12px] font-semibold uppercase text-slate-400">{{ $heading }}</th>
                    @endforeach
                    <th scope="col" class="px-4 py-[11px] text-right text-[12px] font-semibold uppercase text-slate-400">Monto</th>
                    <th scope="col" class="py-[11px]"><span class="sr-only">Comprobante</span></th>
                    <th scope="col" class="px-4 py-[11px] text-[12px] font-semibold uppercase text-slate-400"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    @php
                        $isIncome = $movement->direction === MovementDirection::Income;
                        $struck = $movement->isReversal() || $movement->isReversed();
                        $canReverse = $movement->reversalBlocker() === null;
                    @endphp
                    <tr class="group h-[53px] border-b border-slate-200 bg-white transition-colors hover:bg-slate-50 focus-within:bg-slate-50" x-data>
                        <td class="px-4 py-3 text-[12.5px] text-black">{{ Ui::date($movement->occurred_on) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <span title="{{ $movement->concept }}" @class([
                                    'truncate text-body-md font-semibold',
                                    'text-slate-900' => ! $struck,
                                    'text-slate-400 line-through' => $struck,
                                ])>{{ $movement->concept }}</span>
                                @if ($movement->payment_id)
                                    <x-ui.badge color="blue">Pago</x-ui.badge>
                                @endif
                                @if ($movement->isReversal())
                                    <x-ui.badge color="amber">Reversión</x-ui.badge>
                                @elseif ($movement->isReversed())
                                    <x-ui.badge>Revertido</x-ui.badge>
                                @endif
                            </div>
                        </td>
                        <td class="truncate px-4 py-3 text-body-md text-slate-700">{{ $movement->category?->name }}</td>
                        <td class="truncate px-4 py-3 text-body-md text-slate-700">{{ $movement->branch?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-body-md text-slate-700">
                            <span class="block truncate">{{ $movement->method->label() }}</span>
                            @if ($movement->cashSession)
                                <span class="block truncate text-[11.5px] text-slate-400">{{ $movement->cashSession->register?->name }}</span>
                            @endif
                        </td>
                        <td class="truncate px-4 py-3 text-body-md text-slate-700">{{ $movement->creator?->full_name ?? '—' }}</td>
                        <td @class([
                            'whitespace-nowrap px-4 py-3 text-right font-mono text-body font-bold',
                            'text-emerald-600' => $isIncome && ! $struck,
                            'text-danger-600' => ! $isIncome && ! $struck,
                            'text-slate-400 line-through' => $struck,
                        ])>{{ $isIncome ? '+' : '−' }}{{ Ui::money($movement->amount) }}</td>
                        <td class="py-3">
                            @if ($movement->receipt_storage_key)
                                <a href="{{ route('finances.movements.receipt', $movement) }}" title="Descargar comprobante" aria-label="Descargar comprobante de {{ $movement->concept }}"
                                    class="flex size-8 items-center justify-center rounded-md hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <x-ui.icon name="import" :size="20" />
                                </a>
                            @endif
                        </td>
                        <td class="px-2 py-2">
                            <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
                                <button type="button" title="Ver detalle" aria-label="Ver detalle de {{ $movement->concept }}"
                                    class="shrink-0 rounded-md hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                    x-on:click="$dispatch('open-modal', { name: 'show-movement', movement: @js($movement->toDetailArray()) })">
                                    <x-ui.icon name="action-view" :size="30" />
                                </button>
                                @if ($canReverse)
                                    <button type="button" title="Revertir"
                                        class="h-8 shrink-0 rounded-md px-2 text-[12.5px] font-semibold text-danger-600 hover:bg-danger-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500/50"
                                        x-on:click="$dispatch('open-modal', { name: 'reverse-movement', id: @js($movement->id), label: @js($movement->concept.' ('.($isIncome ? '+' : '−').Ui::money($movement->amount).')'), action: @js(route('finances.movements.reverse', $movement)) })">
                                        Revertir
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center">
                            <p class="text-body font-semibold text-slate-900">
                                {{ $hasFilters ? 'No hay movimientos que coincidan con los filtros' : 'Aún no hay movimientos registrados' }}
                            </p>
                            <p class="mt-1 text-body-md text-slate-500">
                                {{ $hasFilters ? 'Ajusta o limpia los filtros.' : 'Registra el primer ingreso o egreso de la clínica.' }}
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
        @include('finances.partials.movement-modals')
    @endpush
</x-finances.shell>
