{{--
    Odontograma FDI de solo lectura (estado actual del expediente; se edita en el módulo 6).
    Variables: $findings (Collection<int fdi, string condition>)
--}}
@php
    $styles = [
        'sano' => ['border-emerald-200 bg-emerald-50 text-emerald-700', '·', 'Sano'],
        'caries' => ['border-danger-500/30 bg-danger-50 text-danger-700', 'C', 'Caries'],
        'endodoncia' => ['border-violet-200 bg-violet-50 text-violet-700', 'E', 'Endodoncia'],
        'restauracion' => ['border-primary-100 bg-primary-50 text-primary-700', 'R', 'Restauración'],
        'ausente' => ['border-slate-300 bg-slate-200 text-slate-500', 'X', 'Ausente'],
    ];
    $quadrants = [[range(18, 11), range(21, 28)], [range(48, 41), range(31, 38)]];
    if ($findings->keys()->contains(fn ($fdi) => $fdi >= 51)) {
        $quadrants = [...$quadrants, [range(55, 51), range(61, 65)], [range(85, 81), range(71, 75)]];
    }
@endphp

@if ($findings->isEmpty())
    <p class="rounded-lg bg-slate-50 px-3 py-6 text-center text-body-md text-slate-500">Sin hallazgos registrados en el expediente.</p>
@else
    <div class="flex flex-col gap-1" role="img" aria-label="Odontograma: {{ $findings->map(fn ($c, $fdi) => "diente {$fdi} ".($styles[$c][2] ?? $c))->implode(', ') }}">
        @foreach ($quadrants as [$left, $right])
            <div class="flex gap-1.5">
                @foreach ([$left, $right] as $teeth)
                    <div class="flex flex-1 gap-0.5">
                        @foreach ($teeth as $tooth)
                            @php [$tone, $letter, $label] = $styles[$findings->get($tooth)] ?? ['border-slate-200 bg-white text-slate-300', '', 'Sin registro']; @endphp
                            <div class="flex min-w-0 flex-1 flex-col items-center rounded border py-0.5 text-[9px] leading-3 {{ $tone }}" title="Diente {{ $tooth }}: {{ $label }}">
                                <span class="text-slate-400">{{ $tooth }}</span>
                                <span class="h-3 font-bold">{{ $letter }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-500">
        @foreach ($styles as [$tone, $letter, $label])
            <span class="flex items-center gap-1"><span class="inline-block size-2.5 rounded-sm border {{ $tone }}"></span>{{ $label }}</span>
        @endforeach
    </div>
@endif
<p class="mt-2 text-[11px] text-slate-400">Solo lectura · se edita en el expediente del paciente.</p>
