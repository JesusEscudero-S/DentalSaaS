{{-- Importe con signo tipográfico: "−$50.00" (o "+$50.00" con signed). Ui::money() imprimiría "$-50.00". --}}
@props(['amount', 'signed' => false])
@php
    $value = (string) ($amount ?? '0') === '' ? '0' : (string) $amount;
    $cmp = bccomp($value, '0', 2);
@endphp
{{ $cmp < 0 ? '−' : ($signed && $cmp > 0 ? '+' : '') }}{{ \App\Support\Ui::money(ltrim($value, '-')) }}
