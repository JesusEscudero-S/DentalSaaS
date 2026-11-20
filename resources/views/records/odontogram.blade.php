{{--
    Pestaña "Odontograma" (Figma: Expediente - Odontograma). RF 6.3.
    Clic en una pieza → modal para cambiar su estado y caras. Debajo, el control de
    aplicaciones de flúor (RF 6.6, como en la hoja de papel de la clínica) y el historial de cambios.
--}}
@php
    use App\Enums\Dentition;
    use App\Enums\ToothCondition;
    use App\Enums\ToothSurface;
    use App\Support\Ui;

    $segment = 'rounded-md px-3 py-1 transition-colors';
@endphp

<x-records.shell :patient="$patient" :record="$record" :doctors="$doctors" active="odontograma" section="Odontograma">
    <div class="flex flex-col gap-4">
        <x-ui.card class="px-5 py-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-[16px] font-semibold text-slate-900">Odontograma</h2>
                    <p class="mt-1 text-body-md text-slate-500">Estado por pieza dental (numeración FDI). Haz clic en una pieza para actualizarla.</p>
                </div>
                {{-- Dentición permanente / temporal (las piezas de leche no están en Figma; sí en la BD) --}}
                <nav class="flex rounded-lg bg-slate-100 p-0.5 text-[12.5px] font-semibold" aria-label="Dentición">
                    @foreach (Dentition::cases() as $option)
                        <a href="{{ route('records.odontogram.index', [$patient, 'denticion' => $option->value]) }}"
                            @class([$segment, 'bg-white text-slate-900 shadow-sm' => $option === $dentition, 'text-slate-500 hover:text-slate-900' => $option !== $dentition])
                            @if ($option === $dentition) aria-current="true" @endif>{{ $option->label() }}</a>
                    @endforeach
                </nav>
            </div>

            <div class="mt-4 flex flex-col gap-1.5 overflow-x-auto pb-1">
                @foreach ($dentition->rows() as $row)
                    <div class="flex gap-1.5">
                        @foreach ($row as $tooth)
                            @php
                                $finding = $findings[$tooth] ?? null;
                                $condition = $finding?->condition ?? ToothCondition::Sano;
                                $payload = ['tooth' => $tooth, 'condition' => $condition->value, 'surfaces' => $finding?->surfaces ?? []];
                            @endphp
                            {{-- Separación entre cuadrantes (después de la 8.ª pieza permanente / 5.ª temporal) --}}
                            @if ($loop->index === count($row) / 2)
                                <span class="w-1 shrink-0" aria-hidden="true"></span>
                            @endif
                            <button type="button" x-data
                                x-on:click="$dispatch('open-modal', { name: 'tooth', ...@js($payload) })"
                                title="{{ $finding?->description() ?? 'Sano' }}"
                                class="flex h-[52px] min-w-[38px] max-w-[56px] flex-1 flex-col items-center justify-center rounded-lg border transition-shadow hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 {{ $condition->classes() }}">
                                <span class="text-[14px] font-semibold text-slate-900">{{ $tooth }}</span>
                                <span class="max-w-full truncate px-0.5 text-[10px]">{{ $condition->label() }}</span>
                                @if ($finding?->surfaces)
                                    <span class="font-mono text-[9px] leading-none">{{ implode('', $finding->surfaces) }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endforeach
            </div>

            {{-- Leyenda --}}
            <div class="mt-3 flex flex-wrap gap-4 text-[12px] text-slate-500">
                @foreach ($conditions as $option)
                    <span class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm border {{ $option->swatch() }}"></span>{{ $option->label() }}
                    </span>
                @endforeach
                <span class="text-slate-400">Caras: V vestibular · L lingual · M mesial · D distal · O oclusal</span>
            </div>
        </x-ui.card>

        <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] items-start gap-4">
            @include('records.partials.fluoride-card')

            {{-- Historial de cambios del odontograma --}}
            <x-ui.card class="px-5 py-5">
                <h2 class="text-[16px] font-semibold text-slate-900">Cambios recientes</h2>
                @forelse ($history as $item)
                    <div class="mt-3 flex items-start justify-between gap-3 text-body-md">
                        <div class="min-w-0">
                            <p @class(['text-slate-900', 'text-slate-400 line-through' => $item->superseded_at])>
                                <span class="font-semibold">Pieza {{ $item->tooth_fdi }}</span> · {{ $item->description() }}
                            </p>
                            <p class="text-[12px] text-slate-500">{{ Ui::date($item->recorded_at) }} {{ $item->recorded_at->format('H:i') }} · {{ $item->author->displayName() }}</p>
                        </div>
                        @unless ($item->superseded_at)
                            <span class="shrink-0 rounded bg-emerald-50 px-1.5 text-[11px] font-semibold text-emerald-700">Vigente</span>
                        @endunless
                    </div>
                @empty
                    <p class="mt-2 text-body-md text-slate-500">Todas las piezas están como "Sano". Los cambios aparecerán aquí.</p>
                @endforelse
            </x-ui.card>
        </div>
    </div>

    @push('modals')
        @include('records.partials.tooth-modal')
        @include('records.partials.fluoride-modals')
    @endpush
</x-records.shell>
