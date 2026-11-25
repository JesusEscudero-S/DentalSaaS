{{-- Módulo 7, RN-T7: plan, autorización y resumen del tratamiento (DomPDF: CSS 2.1). --}}
@php
    use App\Services\Treatments\TreatmentPlan;
    use App\Support\Ui;

    $procedures = $plan->procedures;
    $realCost = $plan->realCost();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Tratamiento {{ $plan->quote->folio }}</title>
    <style>
        @page { margin: 16mm 15mm 18mm 15mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #0f172a; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        .header { border-bottom: 2pt solid #2563eb; margin-bottom: 8pt; }
        .header td { vertical-align: bottom; padding-bottom: 6pt; }
        .clinic { font-size: 12pt; font-weight: bold; }
        .title { font-size: 13pt; font-weight: bold; color: #2563eb; text-align: right; }
        .muted { color: #64748b; font-size: 7.5pt; }
        .section { margin-top: 10pt; page-break-inside: avoid; }
        .section-title { font-size: 7.5pt; font-weight: bold; text-transform: uppercase; color: #475569; background: #f1f5f9; padding: 3pt 5pt; margin-bottom: 4pt; }
        .grid th { background: #f8fafc; color: #475569; font-size: 7.5pt; text-align: left; padding: 3pt 4pt; border: 0.75pt solid #cbd5e1; }
        .grid td { padding: 3pt 4pt; border: 0.75pt solid #cbd5e1; vertical-align: top; }
        .right { text-align: right; }
        .box { border: 0.75pt solid #cbd5e1; padding: 5pt 6pt; }
        .signatures { margin-top: 40pt; }
        .signatures td { width: 50%; text-align: center; padding: 0 12pt; }
        .line { border-top: 0.75pt solid #0f172a; padding-top: 3pt; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td><div class="clinic">{{ $organization?->commercial_name }}</div><div class="muted">Generado el {{ now()->format('d/m/Y H:i') }}</div></td>
            <td class="title">Plan de tratamiento<br><span class="muted">{{ $plan->quote->folio }} · {{ $plan->status->label() }}</span></td>
        </tr>
    </table>

    <table>
        <tr>
            <td><span class="muted">Paciente</span><br><b>{{ $patient->full_name }}</b> ({{ $patient->folio }})</td>
            <td><span class="muted">Odontólogo responsable</span><br>{{ $plan->doctor()?->displayName() }}</td>
            <td><span class="muted">Fecha del diagnóstico</span><br>{{ Ui::date($plan->diagnosedOn()) }}</td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Diagnóstico dental</div>
        <div class="box">{!! nl2br(e($plan->diagnosis)) !!}</div>
    </div>

    <div class="section">
        <div class="section-title">Procedimientos</div>
        <table class="grid">
            <thead><tr><th>#</th><th>Procedimiento</th><th>Pieza</th><th>Estatus</th><th class="right">Costo</th></tr></thead>
            <tbody>
                @foreach ($procedures as $pt)
                    <tr>
                        <td>{{ $pt->quoteItem->line_number }}</td>
                        <td>{{ $pt->quoteItem->description_snapshot }}</td>
                        <td>{{ $pt->tooth_fdi ?? '—' }}</td>
                        <td>{{ ['planeado' => 'Pendiente', 'en_progreso' => 'En proceso', 'realizado' => 'Realizado', 'cancelado' => 'Cancelado'][$pt->status] ?? $pt->status }}</td>
                        <td class="right">{{ Ui::money($pt->quoteItem->line_total) }}</td>
                    </tr>
                @endforeach
                <tr><td colspan="4" class="right"><b>Costo estimado</b></td><td class="right"><b>{{ Ui::money($plan->quote->total) }}</b></td></tr>
                <tr><td colspan="4" class="right">Realizado (costo real)</td><td class="right">{{ Ui::money($realCost) }}</td></tr>
            </tbody>
        </table>
    </div>

    @if ($plan->sessions->isNotEmpty())
        <div class="section">
            <div class="section-title">Desarrollo de tratamiento</div>
            <table class="grid">
                <thead><tr><th style="width: 14%">Fecha</th><th>Actividades realizadas</th><th style="width: 22%">Responsable</th></tr></thead>
                <tbody>
                    @foreach ($plan->sessions->sortBy(fn ($n) => $n->encounter?->occurred_at) as $session)
                        <tr>
                            <td>{{ Ui::date($session->encounter?->occurred_at) }}</td>
                            <td>
                                {!! nl2br(e(TreatmentPlan::body($session))) !!}
                                @foreach ($session->addenda as $addendum)
                                    <br><i>Adenda: {{ $addendum->content }}</i>
                                @endforeach
                            </td>
                            <td>{{ $session->responsibleDoctor?->displayName() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="section">
        <div class="section-title">Autorización del paciente</div>
        @if ($plan->consent)
            <p>Autorizado por <b>{{ $plan->consent->accepted_by_name }}</b> el {{ $plan->consent->accepted_at->format('d/m/Y H:i') }} ({{ $plan->consent->method->label() }}).</p>
        @else
            <p>Declaro que se me explicó el diagnóstico, los procedimientos propuestos y su costo estimado, y autorizo que se realicen.</p>
            <table class="signatures">
                <tr>
                    <td><div class="line">{{ $patient->full_name }}<br><span class="muted">Paciente o tutor</span></div></td>
                    <td><div class="line">{{ $plan->doctor()?->displayName() }}<br><span class="muted">Odontólogo responsable</span></div></td>
                </tr>
            </table>
        @endif
    </div>
</body>
</html>
