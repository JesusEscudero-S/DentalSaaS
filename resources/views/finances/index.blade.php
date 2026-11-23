{{-- Finanzas › Resumen: KPIs, ingresos vs. egresos de 6 meses y desglose por categoría --}}
@php
    use App\Enums\MovementDirection;
    use App\Support\Ui;

    $dateInput = 'h-9 rounded-lg border border-slate-300 bg-surface px-3 text-[13.5px] text-slate-900 hover:border-slate-400 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/15';
    $branchOptions = $branches->pluck('name', 'id')->all();
    $hasFilters = $period['custom'] || $period['mes'] !== now()->format('Y-m') || $branch !== '';
    $movementsUrl = route('finances.movements.index', array_filter([
        'desde' => $period['from']->toDateString(), 'hasta' => $period['to']->toDateString(), 'sucursal' => $branch,
    ]));

    // Escala de la gráfica: el mayor valor de los 6 meses redondeado hacia arriba a un número "limpio"
    // (80k, 150k…) es el 100 %. Solo es para dibujar; los importes se muestran con bcmath.
    $peak = (float) collect($series)->flatMap(fn ($m) => [$m['income'], $m['expense']])
        ->reduce(fn ($carry, $value) => bccomp($value, $carry, 2) > 0 ? $value : $carry, '0.00');
    $max = '0.00';
    if ($peak > 0) {
        $magnitude = 10 ** floor(log10($peak));
        $step = collect([1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10])->first(fn ($s) => $peak <= $s * $magnitude);
        $max = number_format($step * $magnitude, 2, '.', '');
    }
    $height = fn (string $value) => bccomp($max, '0', 2) > 0 && bccomp($value, '0', 2) > 0
        ? 'max(2px, '.bcdiv(bcmul($value, '100', 4), $max, 2).'%)'
        : '0';
    $compact = fn (string $value) => bccomp($value, '1000', 2) >= 0 ? '$'.rtrim(rtrim(bcdiv($value, '1000', 1), '0'), '.').'k' : Ui::money($value);

    $netPositive = bccomp($current['net'], '0', 2) >= 0;
    $kpis = [
        ['label' => 'Ingresos', 'amount' => $current['income'], 'tone' => 'text-slate-900', 'change' => $changes['income'], 'goodWhenUp' => true],
        ['label' => 'Egresos', 'amount' => $current['expense'], 'tone' => 'text-slate-900', 'change' => $changes['expense'], 'goodWhenUp' => false],
        ['label' => 'Balance', 'amount' => $current['net'], 'tone' => $netPositive ? 'text-emerald-600' : 'text-danger-600', 'change' => $changes['net'], 'goodWhenUp' => true],
    ];
@endphp

<x-finances.shell tab="summary" section="Resumen" :subtitle="$period['label']">
    {{-- Filtros: periodo y sucursal --}}
    <form method="GET" action="{{ route('finances.index') }}" class="mb-4 flex flex-wrap items-center gap-2.5" x-data>
        <x-ui.select name="mes" compact :options="$monthOptions" :value="$period['mes']"
            :placeholder="$period['custom'] ? 'Rango personalizado' : null" aria-label="Mes"
            x-on:change="$el.form.desde.value = ''; $el.form.hasta.value = ''; $el.form.submit()" />
        <span class="text-body-md text-slate-400">o del</span>
        <input type="date" name="desde" value="{{ $period['desde'] }}" aria-label="Desde" class="{{ $dateInput }}" x-on:change="$el.form.submit()">
        <span class="text-body-md text-slate-400">al</span>
        <input type="date" name="hasta" value="{{ $period['hasta'] }}" aria-label="Hasta" class="{{ $dateInput }}" x-on:change="$el.form.submit()">
        @if (count($branchOptions) > 1)
            <x-ui.select name="sucursal" compact :options="$branchOptions" placeholder="Todas las sucursales" :value="$branch"
                aria-label="Sucursal" x-on:change="$el.form.submit()" />
        @endif
        @if ($hasFilters)
            <a href="{{ route('finances.index') }}" class="text-body-md font-semibold text-primary-600 hover:underline">Mes actual</a>
        @endif
    </form>

    {{-- KPIs --}}
    <div class="mb-4 grid grid-cols-4 gap-4">
        @foreach ($kpis as $kpi)
            @php
                $change = $kpi['change'];
                $up = $change !== null && bccomp($change, '0', 1) > 0;
                $flat = $change !== null && bccomp($change, '0', 1) === 0;
                $good = $flat ? null : ($up === $kpi['goodWhenUp']);
            @endphp
            <x-ui.card class="px-5 py-4">
                <p class="text-overline uppercase text-slate-400">{{ $kpi['label'] }}</p>
                <p class="mt-1 font-mono text-[22px] font-bold leading-8 {{ $kpi['tone'] }}"><x-finances.money :amount="$kpi['amount']" /></p>
                <p class="mt-1 text-xs text-slate-500">
                    @if ($change === null)
                        Sin datos para comparar
                    @else
                        <span @class(['font-semibold', 'text-emerald-600' => $good === true, 'text-danger-600' => $good === false, 'text-slate-500' => $good === null])>
                            {{ $flat ? '=' : ($up ? '▲' : '▼') }} {{ ltrim($change, '-') }}%
                        </span>
                        {{ $period['compare_label'] }}
                    @endif
                </p>
            </x-ui.card>
        @endforeach
        <x-ui.card class="px-5 py-4">
            <p class="text-overline uppercase text-slate-400">Cajas abiertas</p>
            <p class="mt-1 font-mono text-[22px] font-bold leading-8 text-slate-900">{{ $openCash['count'] }}</p>
            <p class="mt-1 text-xs text-slate-500">
                Apertura total {{ Ui::money($openCash['opening']) }} ·
                <a href="{{ route('finances.cash.index') }}" class="font-semibold text-primary-600 hover:underline">Ir a Caja</a>
            </p>
        </x-ui.card>
    </div>

    <div class="mb-4 grid grid-cols-3 gap-4">
        {{-- Ingresos vs. egresos de los últimos 6 meses --}}
        <x-ui.card class="col-span-2 p-5">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-overline uppercase text-slate-400">Ingresos vs. egresos · últimos 6 meses</h2>
                <div class="flex items-center gap-4 text-caption text-slate-600">
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-emerald-600"></span>Ingresos</span>
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-danger-600"></span>Egresos</span>
                </div>
            </div>

            <div class="mt-4 flex gap-3" aria-hidden="true">
                <div class="flex h-56 w-12 shrink-0 flex-col justify-between pb-px text-right font-mono text-[11px] text-slate-400">
                    <span>{{ $compact($max) }}</span>
                    <span>{{ $compact(bcdiv($max, '2', 2)) }}</span>
                    <span>$0</span>
                </div>
                <div class="relative flex-1">
                    <div class="absolute inset-x-0 top-0 border-t border-dashed border-slate-200"></div>
                    <div class="absolute inset-x-0 top-1/2 border-t border-dashed border-slate-200"></div>
                    <div class="relative flex h-56 items-end gap-3 border-b border-slate-300">
                        @foreach ($series as $month)
                            <div class="flex h-full flex-1 items-end justify-center gap-[2px]">
                                <div class="flex h-full w-6 items-end" title="Ingresos {{ $month['label'] }}: {{ Ui::money($month['income']) }}">
                                    <div class="w-full rounded-t-[4px] bg-emerald-600 transition-opacity hover:opacity-80" style="height: {{ $height($month['income']) }}"></div>
                                </div>
                                <div class="flex h-full w-6 items-end" title="Egresos {{ $month['label'] }}: {{ Ui::money($month['expense']) }}">
                                    <div class="w-full rounded-t-[4px] bg-danger-600 transition-opacity hover:opacity-80" style="height: {{ $height($month['expense']) }}"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex gap-3">
                        @foreach ($series as $month)
                            <span class="flex-1 text-center text-caption font-normal text-slate-500">{{ $month['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            <table class="sr-only">
                <caption>Ingresos y egresos de los últimos 6 meses</caption>
                <thead><tr><th scope="col">Mes</th><th scope="col">Ingresos</th><th scope="col">Egresos</th></tr></thead>
                <tbody>
                    @foreach ($series as $month)
                        <tr><th scope="row">{{ $month['label'] }}</th><td>{{ Ui::money($month['income']) }}</td><td>{{ Ui::money($month['expense']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>

        {{-- Últimos movimientos del periodo --}}
        <x-ui.card class="flex flex-col p-5">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-overline uppercase text-slate-400">Últimos movimientos</h2>
                <a href="{{ $movementsUrl }}" class="text-body-md font-semibold text-primary-600 hover:underline">Ver todos</a>
            </div>
            <ul class="mt-3 flex flex-1 flex-col divide-y divide-slate-100">
                @forelse ($latest as $movement)
                    @php $isIncome = $movement->direction === MovementDirection::Income; @endphp
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-body-md font-semibold text-slate-900" title="{{ $movement->concept }}">{{ $movement->concept }}</p>
                            <p class="truncate text-xs text-slate-500">{{ Ui::date($movement->occurred_on) }} · {{ $movement->category?->name }}</p>
                        </div>
                        <span @class(['shrink-0 font-mono text-body-md font-bold', 'text-emerald-600' => $isIncome, 'text-danger-600' => ! $isIncome])>
                            {{ $isIncome ? '+' : '−' }}{{ Ui::money($movement->amount) }}
                        </span>
                    </li>
                @empty
                    <li class="py-10 text-center text-body-md text-slate-500">Sin movimientos en el periodo.</li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>

    {{-- Desglose por categoría --}}
    <div class="grid grid-cols-2 gap-4">
        @foreach ([['Ingresos por categoría', $incomeByCategory, 'bg-emerald-600', 'Sin ingresos en el periodo.'], ['Egresos por categoría', $expenseByCategory, 'bg-danger-600', 'Sin egresos en el periodo.']] as [$title, $breakdown, $barColor, $empty])
            <x-ui.card class="overflow-hidden">
                <div class="flex items-center justify-between gap-4 px-5 pb-3 pt-4">
                    <h2 class="text-overline uppercase text-slate-400">{{ $title }}</h2>
                    <span class="font-mono text-body-md font-bold text-slate-900">{{ Ui::money($breakdown['total']) }}</span>
                </div>
                <table class="w-full table-fixed border-collapse">
                    <colgroup><col class="w-[38%]"><col class="w-[22%]"><col class="w-[12%]"><col class="w-[28%]"></colgroup>
                    <thead>
                        <tr class="border-y border-slate-200 bg-slate-50 text-left">
                            <th scope="col" class="px-5 py-[9px] text-[12px] font-semibold uppercase text-slate-400">Categoría</th>
                            <th scope="col" class="px-4 py-[9px] text-right text-[12px] font-semibold uppercase text-slate-400">Monto</th>
                            <th scope="col" class="px-4 py-[9px] text-right text-[12px] font-semibold uppercase text-slate-400">%</th>
                            <th scope="col" class="px-5 py-[9px]"><span class="sr-only">Proporción</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($breakdown['rows'] as $row)
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="truncate px-5 py-2.5 text-body-md text-slate-900" title="{{ $row['name'] }}">{{ $row['name'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono text-body-md text-slate-900">{{ Ui::money($row['total']) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-body-md text-slate-500">{{ $row['share'] }}%</td>
                                <td class="px-5 py-2.5">
                                    <div class="h-1.5 w-full rounded-full bg-slate-100">
                                        <div class="h-1.5 rounded-full {{ $barColor }}" style="width: {{ $row['share'] }}%"></div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-body-md text-slate-500">{{ $empty }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.card>
        @endforeach
    </div>
</x-finances.shell>
