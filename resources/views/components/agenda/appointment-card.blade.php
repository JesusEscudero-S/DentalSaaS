{{-- Tarjeta de cita en el calendario (Figma: Appointment). --}}
@props(['appointment'])

@php
    $pos = \App\Services\Agenda\AgendaService::position($appointment);
    $color = \App\Support\Ui::colorFor($appointment->doctor_member_id);   // mismo color por doctor

    // Tailwind necesita ver las clases completas escritas en el código: por eso un mapa y no "bg-{$color}-50".
    $colors = [
        'blue' => 'bg-blue-50 border-blue-500',
        'sky' => 'bg-sky-50 border-sky-500',
        'green' => 'bg-emerald-50 border-emerald-500',
        'violet' => 'bg-violet-50 border-violet-500',
        'amber' => 'bg-amber-50 border-amber-500',
        'slate' => 'bg-slate-50 border-slate-400',
    ];
@endphp

<div class="absolute inset-x-1 overflow-hidden rounded-md border-l-[3px] px-2 py-1 {{ $colors[$color] }}"
    style="top: {{ $pos['top'] }}px; height: {{ $pos['height'] }}px">
    <p class="font-mono text-[10px] leading-4 text-slate-600">{{ $appointment->starts_at->format('H:i') }}</p>
    <p class="truncate text-[12px] font-bold leading-4 text-slate-900">{{ $appointment->patient->full_name }}</p>
    <p class="truncate text-[11px] leading-4 text-slate-500">{{ $appointment->notes }}</p>
</div>