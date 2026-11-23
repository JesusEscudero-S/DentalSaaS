{{-- Vista Mes (no está en Figma: se armó con los mismos tokens que la vista Semana).
     $days llega como semanas: [[lun..dom], [lun..dom], ...] (AgendaService::monthWeeks).
     Cada día muestra cuántas citas tiene y las primeras 3; el número del día lleva a la vista Día. --}}
@php
    $maxPerDay = 3;
@endphp

<x-ui.card class="overflow-hidden">
    <div class="grid grid-cols-7">
        {{-- Encabezados: LUN … DOM --}}
        @foreach ($days[0] as $day)
            <div class="border-b border-slate-200 py-2 text-center text-[11px] font-semibold uppercase text-slate-500 [&:not(:first-child)]:border-l">
                {{ rtrim($day->translatedFormat('D'), '.') }}
            </div>
        @endforeach

        @foreach ($days as $week)
            @foreach ($week as $day)
                @php
                    $appointments = $appointmentsByDay[$day->toDateString()] ?? collect();
                    $inMonth = $day->isSameMonth($anchor);
                @endphp

                <div @class([
                    'flex min-h-[112px] flex-col gap-1 border-b border-slate-200 p-1.5',
                    'border-l' => ! $loop->first,
                    'bg-slate-50' => ! $inMonth,
                    'bg-primary-50/60' => $day->isToday(),
                ])>
                    <div class="flex items-center justify-between px-0.5">
                        <a href="{{ $dayUrl($day) }}" title="Ver el {{ $day->translatedFormat('l j \d\e F') }}"
                            @class([
                                'flex size-6 items-center justify-center rounded-full text-[12px] font-semibold hover:bg-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
                                'bg-primary-600 text-white hover:bg-primary-600' => $day->isToday(),
                                'text-slate-900' => $inMonth && ! $day->isToday(),
                                'text-slate-400' => ! $inMonth,
                            ])>{{ $day->day }}</a>

                        @if ($appointments->isNotEmpty())
                            <span class="text-[11px] font-semibold text-slate-500">
                                {{ $appointments->count() }} {{ $appointments->count() === 1 ? 'cita' : 'citas' }}
                            </span>
                        @endif
                    </div>

                    {{-- Primeras citas del día: clic = mismo modal "Detalle de cita" que en la semana --}}
                    @foreach ($appointments->take($maxPerDay) as $appointment)
                        @php $late = $appointment->isLate(); @endphp
                        <button type="button" x-data
                            x-on:click="$dispatch('open-modal', { name: 'appointment-detail', ...@js($appointment->toDetailArray()) })"
                            @class([
                                'flex w-full items-center gap-1.5 rounded px-1 py-0.5 text-left text-[11px] leading-4 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
                                'text-danger-600' => $late,
                                'text-slate-700' => ! $late,
                            ])
                            aria-label="Ver cita de {{ $appointment->patient->full_name }} a las {{ $appointment->starts_at->format('H:i') }}">
                            <x-ui.color-dot :color="$late ? 'red' : ($doctorColors[$appointment->doctor_member_id] ?? 'slate')" />
                            <span class="font-mono text-[10px] text-slate-500">{{ $appointment->starts_at->format('H:i') }}</span>
                            <span class="truncate font-semibold">{{ $appointment->patient->full_name }}</span>
                        </button>
                    @endforeach

                    @if ($appointments->count() > $maxPerDay)
                        <a href="{{ $dayUrl($day) }}" class="px-1 text-[11px] font-semibold text-primary-600 hover:underline">
                            +{{ $appointments->count() - $maxPerDay }} más
                        </a>
                    @endif
                </div>
            @endforeach
        @endforeach
    </div>
</x-ui.card>
