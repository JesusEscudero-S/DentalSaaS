{{-- Finanzas › Caja: cajas por sucursal e historial de cortes (RF-06 a RF-08) --}}
@php
    $groups = $registers->groupBy('branch_id')->sortByDesc(fn ($group) => (int) $group->first()->branch?->is_main);
@endphp

<x-finances.shell tab="cash" section="Caja" subtitle="Apertura y corte de caja por sucursal">
    <x-slot:actions>
        <x-ui.button size="lg" x-data x-on:click="$dispatch('open-modal', { name: 'create-register' })">
            <x-ui.icon name="add" :size="16" />
            Nueva caja
        </x-ui.button>
    </x-slot:actions>

    @forelse ($groups as $group)
        <section class="mb-6">
            <h2 class="mb-3 text-overline uppercase text-slate-400">{{ $group->first()->branch?->name }}</h2>
            <div class="grid grid-cols-3 gap-4">
                @foreach ($group as $register)
                    @include('finances.partials.register-card', ['expectedCash' => $expected[$register->openSession?->id] ?? null])
                @endforeach
            </div>
        </section>
    @empty
        <x-ui.card class="mb-6 px-6 py-12 text-center">
            <p class="text-body font-semibold text-slate-900">Aún no hay cajas</p>
            <p class="mt-1 text-body-md text-slate-500">Crea una caja para registrar movimientos en efectivo y hacer cortes.</p>
        </x-ui.card>
    @endforelse

    <h2 class="mb-3 text-overline uppercase text-slate-400">Historial de cortes</h2>
    <x-ui.card class="overflow-hidden">
        <table class="w-full table-fixed border-collapse">
            <colgroup>
                <col class="w-[14%]"><col class="w-[11%]"><col class="w-[11%]"><col class="w-[11%]"><col class="w-[11%]">
                <col class="w-[9%]"><col class="w-[9%]"><col class="w-[9%]"><col class="w-[9%]"><col class="w-[6%]">
            </colgroup>
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50 text-left">
                    @foreach (['Caja', 'Apertura', 'Cierre', 'Abrió', 'Cerró'] as $heading)
                        <th scope="col" class="px-4 py-[11px] text-[12px] font-semibold uppercase text-slate-400">{{ $heading }}</th>
                    @endforeach
                    @foreach (['Esperado', 'Contado', 'Diferencia', 'Ajustes'] as $heading)
                        <th scope="col" class="px-4 py-[11px] text-right text-[12px] font-semibold uppercase text-slate-400">{{ $heading }}</th>
                    @endforeach
                    <th scope="col" class="px-4 py-[11px]"><span class="sr-only">Ver</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($history as $cut)
                    @php $diff = bccomp((string) $cut->difference, '0', 2); @endphp
                    <tr class="h-[53px] border-b border-slate-200 bg-white transition-colors hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <span class="block truncate text-body-md font-semibold text-slate-900">{{ $cut->register?->name }}</span>
                            <span class="block truncate text-[11.5px] text-slate-400">{{ $cut->register?->branch?->name }}</span>
                        </td>
                        <td class="px-4 py-3 text-[12.5px] text-slate-700">{{ $cut->opened_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-[12.5px] text-slate-700">{{ $cut->closed_at?->format('d/m/Y H:i') }}</td>
                        <td class="truncate px-4 py-3 text-body-md text-slate-700">{{ $cut->openedBy?->full_name ?? '—' }}</td>
                        <td class="truncate px-4 py-3 text-body-md text-slate-700">{{ $cut->closedBy?->full_name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-body-md text-slate-700"><x-finances.money :amount="$cut->expected_cash" /></td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-body-md text-slate-700"><x-finances.money :amount="$cut->counted_cash" /></td>
                        <td @class([
                            'whitespace-nowrap px-4 py-3 text-right font-mono text-body-md font-bold',
                            'text-emerald-600' => $diff === 0,
                            'text-amber-600' => $diff > 0,
                            'text-danger-600' => $diff < 0,
                        ])><x-finances.money :amount="$cut->difference" signed /></td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-body-md text-slate-700">
                            @if ($cut->adjustments_sum_amount !== null)
                                <x-finances.money :amount="$cut->adjustments_sum_amount" signed />
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-2 py-2">
                            <a href="{{ route('finances.cash.sessions.show', $cut) }}" title="Ver corte" aria-label="Ver corte de {{ $cut->register?->name }}"
                                class="flex rounded-md hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                <x-ui.icon name="action-view" :size="30" />
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center">
                            <p class="text-body font-semibold text-slate-900">Aún no hay cortes de caja</p>
                            <p class="mt-1 text-body-md text-slate-500">Cuando hagas el corte de una caja abierta, aparecerá aquí.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="flex items-center justify-between gap-4 px-3 py-4">
            <p class="text-[12px] text-slate-600">
                @if ($history->total())
                    Mostrando {{ $history->firstItem() }}-{{ $history->lastItem() }} de {{ $history->total() }} {{ $history->total() === 1 ? 'corte' : 'cortes' }}
                @endif
            </p>
            {{ $history->links() }}
        </div>
    </x-ui.card>

    @push('modals')
        @include('finances.partials.register-modals')
        @include('finances.partials.session-modals')
    @endpush
</x-finances.shell>
