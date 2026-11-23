{{--
    Base de los PDF de Documentos (DomPDF: CSS 2.1, sin Tailwind; los colores replican los tokens slate/primary).
    Variables: $organization, $template, $patient (nullable), $date (nullable)
--}}
@php
    use App\Support\Ui;

    $contact = array_filter([
        $organization?->address_line,
        $organization?->phone ? 'Tel. '.Ui::phone($organization->phone) : null,
        $organization?->email,
    ]);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $template->label() }}</title>
    <style>
        @page { margin: 16mm 15mm 18mm 15mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #0f172a; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        .header { border-bottom: 2pt solid #2563eb; margin-bottom: 8pt; }
        .header td { vertical-align: bottom; padding-bottom: 6pt; }
        .clinic { font-size: 12pt; font-weight: bold; }
        .muted { color: #64748b; font-size: 7.5pt; }
        .doc-title { font-size: 13pt; font-weight: bold; color: #2563eb; }
        .right { text-align: right; }
        .center { text-align: center; }
        .section { margin-top: 9pt; page-break-inside: avoid; }
        .section-title { font-size: 7.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6pt; color: #475569; background: #f1f5f9; padding: 3pt 5pt; margin-bottom: 4pt; }
        .fields td { padding: 3pt 8pt 1pt 0; vertical-align: bottom; }
        .label { color: #64748b; font-size: 7.5pt; white-space: nowrap; width: 1%; padding-right: 4pt; }
        .line { border-bottom: 0.75pt solid #94a3b8; height: 13pt; }
        .box { border: 0.75pt solid #cbd5e1; padding: 4pt 6pt; min-height: 28pt; }
        .grid th { background: #f8fafc; color: #475569; font-size: 7.5pt; text-align: left; padding: 3pt 4pt; border: 0.75pt solid #cbd5e1; }
        .grid td { padding: 3pt 4pt; border: 0.75pt solid #cbd5e1; height: 12pt; vertical-align: top; }
        .check { white-space: nowrap; padding-right: 10pt; }
        .declaration { border: 0.75pt solid #cbd5e1; background: #f8fafc; padding: 6pt 8pt; text-align: justify; }
        .signatures { margin-top: 38pt; page-break-inside: avoid; }
        .signatures td { padding: 0 10pt; text-align: center; vertical-align: top; }
        .signature-line { border-top: 0.75pt solid #0f172a; padding-top: 3pt; font-size: 7.5pt; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 6.5pt; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">
        {{ $organization?->commercial_name }} · {{ $template->label() }}@if ($patient) · {{ $patient->folio }}@endif
    </div>

    <table class="header">
        <tr>
            <td>
                <div class="clinic">{{ $organization?->commercial_name }}</div>
                @if ($organization?->legal_name || $organization?->rfc)
                    <div class="muted">{{ $organization->legal_name }}@if ($organization->rfc) · RFC {{ $organization->rfc }}@endif</div>
                @endif
                <div class="muted">{{ implode(' · ', $contact) }}</div>
            </td>
            <td class="right">
                <div class="doc-title">{{ $template->label() }}</div>
                <div>Folio del paciente: <strong>{{ $patient?->folio ?? '__________' }}</strong></div>
                <div>Fecha: <strong>{{ $date ? Ui::date($date) : '____ / ____ / ________' }}</strong></div>
            </td>
        </tr>
    </table>

    @yield('content')
</body>
</html>
