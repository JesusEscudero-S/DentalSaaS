{{--
    Odontograma FDI de solo lectura para el PDF.
    Variables: $findings (Collection<int fdi, string condition>) — hallazgos vigentes (superseded_at IS NULL)
--}}
@php
    $styles = [
        'sano' => ['', ''],
        'caries' => ['background: #fee2e2; color: #b91c1c;', 'C'],
        'endodoncia' => ['background: #ede9fe; color: #6d28d9;', 'E'],
        'restauracion' => ['background: #dbeafe; color: #1d4ed8;', 'R'],
        'ausente' => ['background: #e2e8f0; color: #475569;', 'X'],
    ];
    $permanent = [array_merge(range(18, 11), range(21, 28)), array_merge(range(48, 41), range(31, 38))];
    $temporary = [array_merge(range(55, 51), range(61, 65)), array_merge(range(85, 81), range(71, 75))];
    $hasTemporary = $findings->keys()->contains(fn ($fdi) => $fdi >= 51);
@endphp

@foreach ($hasTemporary ? [$permanent, $temporary] : [$permanent] as $dentition)
    <table style="margin-bottom: 4pt; table-layout: fixed;">
        @foreach ($dentition as $row)
            <tr>
                @foreach ($row as $tooth)
                    @php [$style, $letter] = $styles[$findings->get($tooth)] ?? ['', '']; @endphp
                    <td class="center" style="border: 0.75pt solid #cbd5e1; padding: 2pt 0; font-size: 7pt; {{ $style }}">
                        <div style="color: #64748b;">{{ $tooth }}</div>
                        <div style="font-weight: bold; height: 9pt;">{{ $letter }}</div>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
@endforeach
<p class="muted">C = caries · E = endodoncia · R = restauración · X = ausente · sin marca = sano o sin registro</p>
