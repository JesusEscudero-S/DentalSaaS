{{--
    PDF "Autorización de Tratamiento". Sin datos sale el formato en blanco (vista previa).
    Variables: $patient, $doctor, $answers (fluoride[], diagnoses[], activities[] con doctor_name), $date,
               $findings (Collection fdi => condition, opcional), $quote (Quote con items, opcional)
--}}
@extends('documents.pdf.layout')

@php
    use App\Enums\DocumentTemplate;
    use App\Support\Ui;

    $a = $answers ?? [];
    $findings = $findings ?? collect();
    $quote = $quote ?? null;
    $rows = fn (string $key, int $blank) => ($a[$key] ?? []) ?: array_fill(0, $blank, []);
    $license = $doctor?->doctorProfile?->professional_license;
@endphp

@section('content')
    <div class="section">
        <div class="section-title">Datos del paciente</div>
        <table class="fields">
            <tr>
                <td class="label">Nombre</td>
                <td class="line">{{ $patient?->full_name }}</td>
                <td class="label">Edad</td>
                <td class="line" style="width: 15%;">{{ $patient?->age !== null ? $patient->age.' años' : '' }}</td>
                <td class="label">Teléfono</td>
                <td class="line" style="width: 18%;">{{ $patient?->formatted_phone }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Odontograma (estado actual del expediente)</div>
        @if ($patient && $findings->isEmpty())
            <p class="muted">Sin hallazgos registrados en el expediente.</p>
        @endif
        @include('documents.pdf.partials.odontogram', ['findings' => $findings])
    </div>

    <table style="margin-top: 9pt;">
        <tr>
            <td style="width: 38%; padding-right: 8pt; vertical-align: top;">
                <div class="section-title">Aplicaciones de flúor</div>
                <table class="grid">
                    <tr><th style="width: 22%;">Año</th><th>Aplicaciones / detalles</th></tr>
                    @foreach ($rows('fluoride', 3) as $row)
                        <tr><td>{{ $row['year'] ?? '' }}</td><td>{{ $row['details'] ?? '' }}</td></tr>
                    @endforeach
                </table>
            </td>
            <td style="vertical-align: top;">
                <div class="section-title">Diagnóstico dental</div>
                <table class="grid">
                    <tr><th style="width: 18%;">Fecha</th><th>Diagnóstico</th></tr>
                    @foreach ($rows('diagnoses', 3) as $row)
                        <tr>
                            <td>{{ ! empty($row['date']) ? \Carbon\Carbon::parse($row['date'])->format('d/m/Y') : '' }}</td>
                            <td>{{ $row['text'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Desarrollo de tratamiento</div>
        <table class="grid">
            <tr><th style="width: 13%;">Fecha</th><th>Actividades realizadas</th><th style="width: 28%;">Estomatólogo</th></tr>
            @foreach ($rows('activities', 4) as $row)
                <tr>
                    <td>{{ ! empty($row['date']) ? \Carbon\Carbon::parse($row['date'])->format('d/m/Y') : '' }}</td>
                    <td>{{ $row['description'] ?? '' }}</td>
                    <td>{{ $row['doctor_name'] ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="section">
        <div class="section-title">Presupuesto autorizado{{ $quote ? ' · '.$quote->folio : '' }}</div>
        <table class="grid">
            <tr>
                <th>Concepto</th><th style="width: 9%;">Diente</th><th class="right" style="width: 9%;">Cant.</th>
                <th class="right" style="width: 16%;">Precio unitario</th><th class="right" style="width: 16%;">Importe</th>
            </tr>
            @forelse ($quote?->items ?? [] as $item)
                <tr>
                    <td>{{ $item->description_snapshot }}</td>
                    <td>{{ $item->tooth_fdi }}</td>
                    <td class="right">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}</td>
                    <td class="right">{{ Ui::money($item->unit_price) }}</td>
                    <td class="right">{{ Ui::money($item->line_total) }}</td>
                </tr>
            @empty
                @foreach (range(1, 3) as $blank)
                    <tr><td></td><td></td><td></td><td></td><td></td></tr>
                @endforeach
            @endforelse
            <tr>
                <td colspan="4" class="right"><strong>Total</strong></td>
                <td class="right"><strong>{{ $quote ? Ui::money($quote->total) : '' }}</strong></td>
            </tr>
        </table>
    </div>

    {{-- La declaración y las firmas van juntas: una firma nunca queda sola en otra página. --}}
    <div style="page-break-inside: avoid;">
    <div class="section">
        <div class="section-title">Declaración de consentimiento</div>
        <div class="declaration">{{ DocumentTemplate::CONSENT_DECLARATION }}</div>
    </div>

    <table class="signatures">
        <tr>
            <td style="width: 33%;">
                <div class="signature-line">Paciente o tutor<br><strong>{{ $patient?->full_name }}</strong></div>
            </td>
            <td style="width: 34%;">
                <div class="signature-line">
                    Doctor responsable<br><strong>{{ $doctor?->displayName() }}</strong>
                    @if ($license)<br>Céd. Prof. {{ $license }}@endif
                </div>
            </td>
            <td style="width: 33%;">
                <div class="signature-line">Testigo<br>&nbsp;</div>
            </td>
        </tr>
    </table>
    </div>
@endsection
