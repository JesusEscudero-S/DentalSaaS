{{-- Finanzas › Caja › Sesión: resumen, movimientos en efectivo, corte y ajustes (RF-08, RF-09) --}}
@php
    use App\Enums\MovementDirection;
    use App\Support\Ui;

    $register = $session->register;
    $isOpen = $session->isOpen();
    $diff = $isOpen ? null : bccomp((string) $session->difference, '0', 2);
    $adjustmentsTotal = $adjustments->reduce(fn ($carry, $a) => bcadd($carry, (string) $a->amount, 2), '0.00');
    $stat = 'mt-1 font-mono text-[20px] font-bold leading-7';
@endphp

<x-finances.shell tab="cash" section="Sesión de caja" :subtitle="$register->name.' · '.$register->branch?->name"
    :breadcrumbs="['Finanzas' => route('finances.index'), 'Caja' => route('finances.cash.index'), $register->name => null]">
    <x-slot:actions>
        <x-ui.button variant="outline" size="sm" :href="route('finances.cash.index')">Volver a Caja</x-ui.button>
        @if ($isOpen)
            <span x-data x-on:click="$dispatch('open-modal', { name: 'close-session', id: @js($session->id), label: @js($register->name), expected: @js($summary['expected']), action: @js(route('finances.cash.close', $session)) })">
                <x-ui.button size="lg">Hacer corte</x-ui.button>
            </span>
        @else
            <span x-data x-on:click="$dispatch('open-modal', { name: 'create-adjustment', id: @js($session->id), action: @js(route('finances.cash.adjustments.store', $session)) })">
                <x-ui.button size="lg">
                    <x-ui.icon name="add" :size="16" />
                    Registrar ajuste
                </x-ui.button>
            </span>
        @endif
    </x-slot:actions>

    {{-- Encabezado de la sesión --}}
    <x-ui.card class="mb-4 flex items-center gap-4 px-5 py-4">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <p class="text-[16px] font-semibold text-slate-900">{{ $register->name }}</p>
                @if ($isOpen)
                    <x-ui.badge color="green">Abierta</x-ui.badge>
                @else
                    <x-ui.badge>Cerrada</x-ui.badge>
                @endif
            </div>
            <p class="mt-1 text-body-md text-slate-500">
                Abrió {{ $session->openedBy?->full_name ?? '—' }} el {{ Ui::date($session->opened_at) }}, {{ $session->opened_at->format('H:i') }}
                @unless ($isOpen)
                    · Cortó {{ $session->closedBy?->full_name ?? '—' }} el {{ Ui::date($session->closed_at) }}, {{ $session->closed_at?->format('H:i') }}
                @endunless
            </p>
        </div>
    </x-ui.card>

    {{-- Resumen --}}
    <div class="mb-4 grid grid-cols-4 gap-4">
        <x-ui.card class="px-5 py-4">
            <p class="text-overline uppercase text-slate-400">Apertura</p>
            <p class="{{ $stat }} text-slate-900"><x-finances.money :amount="$summary['opening']" /></p>
        </x-ui.card>
        <x-ui.card class="px-5 py-4">
            <p class="text-overline uppercase text-slate-400">Ingresos en efectivo</p>
            <p class="{{ $stat }} text-emerald-600"><x-finances.money :amount="$summary['cash_in']" signed /></p>
        </x-ui.card>
        <x-ui.card class="px-5 py-4">
            <p class="text-overline uppercase text-slate-400">Egresos en efectivo</p>
            <p class="{{ $stat }} text-danger-600"><x-finances.money :amount="'-'.$summary['cash_out']" /></p>
        </x-ui.card>
        <x-ui.card class="px-5 py-4">
            <p class="text-overline uppercase text-slate-400">{{ $isOpen ? 'Esperado al momento' : 'Esperado' }}</p>
            <p class="{{ $stat }} text-slate-900"><x-finances.money :amount="$isOpen ? $summary['expected'] : $session->expected_cash" /></p>
        </x-ui.card>
    </div>

    @unless ($isOpen)
        <div class="mb-4 grid grid-cols-4 gap-4">
            <x-ui.card class="px-5 py-4">
                <p class="text-overline uppercase text-slate-400">Contado</p>
                <p class="{{ $stat }} text-slate-900"><x-finances.money :amount="$session->counted_cash" /></p>
            </x-ui.card>
            <x-ui.card class="px-5 py-4">
                <p class="text-overline uppercase text-slate-400">Diferencia</p>
                <p @class([$stat, 'text-emerald-600' => $diff === 0, 'text-amber-600' => $diff > 0, 'text-danger-600' => $diff < 0])>
                    <x-finances.money :amount="$session->difference" signed />
                </p>
                <p class="text-xs text-slate-500">{{ $diff === 0 ? 'Sin diferencia' : ($diff > 0 ? 'Sobrante' : 'Faltante') }}</p>
            </x-ui.card>
            <x-ui.card class="col-span-2 px-5 py-4">
                <p class="text-overline uppercase text-slate-400">Notas del corte</p>
                <p class="mt-1 whitespace-pre-line text-body-md text-slate-700">{{ $session->notes ?: 'Sin notas.' }}</p>
            </x-ui.card>
        </div>
    @endunless

    {{-- Movimientos en efectivo de la sesión --}}
    <h2 class="mb-3 mt-6 text-overline uppercase text-slate-400">Movimientos en efectivo</h2>
    <x-ui.card class="overflow-hidden">
        <table class="w-full table-fixed border-collapse">
            <colgroup><col class="w-[16%]"><col class="w-[36%]"><col class="w-[18%]"><col class="w-[15%]"><col class="w-[15%]"></colgroup>
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50 text-left">
                    @foreach (['Registrado', 'Concepto', 'Categoría', 'Registró'] as $heading)
                        <th scope="col" class="px-4 py-[11px] text-[12px] font-semibold uppercase text-slate-400">{{ $heading }}</th>
                    @endforeach
                    <th scope="col" class="px-4 py-[11px] text-right text-[12px] font-semibold uppercase text-slate-400">Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    @php
                        $isIncome = $movement->direction === MovementDirection::Income;
                        $struck = $movement->isReversal() || $movement->isReversed();
                    @endphp
                    <tr class="h-[53px] border-b border-slate-200 bg-white">
                        <td class="px-4 py-3 text-[12.5px] text-slate-700">{{ $movement->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <span @class(['truncate text-body-md font-semibold', 'text-slate-900' => ! $struck, 'text-slate-400 line-through' => $struck])>{{ $movement->concept }}</span>
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
                        <td class="truncate px-4 py-3 text-body-md text-slate-700">{{ $movement->creator?->full_name ?? '—' }}</td>
                        <td @class([
                            'whitespace-nowrap px-4 py-3 text-right font-mono text-body font-bold',
                            'text-emerald-600' => $isIncome && ! $struck,
                            'text-danger-600' => ! $isIncome && ! $struck,
                            'text-slate-400 line-through' => $struck,
                        ])>{{ $isIncome ? '+' : '−' }}{{ Ui::money($movement->amount) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-body-md text-slate-500">Esta sesión no tiene movimientos en efectivo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($movements->hasPages())
            <div class="flex items-center justify-between gap-4 px-3 py-4">
                <p class="text-[12px] text-slate-600">Mostrando {{ $movements->firstItem() }}-{{ $movements->lastItem() }} de {{ $movements->total() }} movimientos</p>
                {{ $movements->links() }}
            </div>
        @endif
    </x-ui.card>

    {{-- Ajustes (solo en sesiones cortadas) --}}
    @unless ($isOpen)
        <h2 class="mb-3 mt-6 text-overline uppercase text-slate-400">Ajustes</h2>
        <x-ui.card class="overflow-hidden">
            <table class="w-full table-fixed border-collapse">
                <colgroup><col class="w-[16%]"><col class="w-[54%]"><col class="w-[15%]"><col class="w-[15%]"></colgroup>
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-left">
                        @foreach (['Registrado', 'Motivo', 'Registró'] as $heading)
                            <th scope="col" class="px-4 py-[11px] text-[12px] font-semibold uppercase text-slate-400">{{ $heading }}</th>
                        @endforeach
                        <th scope="col" class="px-4 py-[11px] text-right text-[12px] font-semibold uppercase text-slate-400">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($adjustments as $adjustment)
                        <tr class="h-[53px] border-b border-slate-200 bg-white">
                            <td class="px-4 py-3 text-[12.5px] text-slate-700">{{ $adjustment->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-body-md text-slate-700">{{ $adjustment->reason }}</td>
                            <td class="truncate px-4 py-3 text-body-md text-slate-700">{{ $adjustment->creator?->full_name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-body font-bold text-slate-900"><x-finances.money :amount="$adjustment->amount" signed /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-body-md text-slate-500">Sin ajustes.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($adjustments->isNotEmpty())
                    <tfoot>
                        <tr class="bg-slate-50">
                            <td colspan="3" class="px-4 py-3 text-right text-body-md font-semibold text-slate-700">Total de ajustes</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-body font-bold text-slate-900"><x-finances.money :amount="$adjustmentsTotal" signed /></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </x-ui.card>
    @endunless

    @push('modals')
        @include('finances.partials.session-modals')
    @endpush
</x-finances.shell>
