{{-- Tarjeta de cita en el calendario (Figma: Appointment).
     $color llega desde la vista (AgendaService::doctorColors) para que coincida con el chip del doctor.
     Al dar clic abre el modal "Detalle de cita" con los datos de toDetailArray(). --}}
@props(['appointment', 'color' => 'slate'])

@php
    $pos = \App\Services\Agenda\AgendaService::position($appointment);
    $late = $appointment->isLate();   // RN-C5: +30 min sin iniciar → rojo

    // Tailwind necesita ver las clases completas escritas en el código: por eso un mapa y no "bg-{$color}-50".
    $colors = [
        'blue' => 'bg-blue-50 border-blue-500',
        'sky' => 'bg-sky-50 border-sky-500',
        'green' => 'bg-emerald-50 border-emerald-500',
        'violet' => 'bg-violet-50 border-violet-500',
        'amber' => 'bg-amber-50 border-amber-500',
        'slate' => 'bg-slate-50 border-slate-400',
    ];
    $tone = $late ? 'bg-danger-50 border-danger-500 ring-1 ring-danger-500/40' : $colors[$color];
@endphp

<button type="button" x-data
    x-on:click="$dispatch('open-modal', { name: 'appointment-detail', ...@js($appointment->toDetailArray()) })"
    class="absolute inset-x-1 overflow-hidden rounded-md border-l-[3px] px-2 py-1 text-left transition-shadow hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 {{ $tone }}"
    style="top: {{ $pos['top'] }}px; height: {{ $pos['height'] }}px"
    aria-label="Ver cita de {{ $appointment->patient->full_name }} a las {{ $appointment->starts_at->format('H:i') }}">
    <p class="flex items-center gap-1 font-mono text-[10px] leading-4 text-slate-600">
        {{ $appointment->starts_at->format('H:i') }}
        @if ($late)
            <span class="rounded bg-danger-500 px-1 font-sans text-[9px] font-bold uppercase text-white">Retraso</span>
        @elseif ($appointment->status !== \App\Enums\AppointmentStatus::Pendiente)
            <span class="font-sans text-[9px] font-semibold uppercase text-slate-500">· {{ $appointment->status->label() }}</span>
        @endif
    </p>
    <p class="truncate text-[12px] font-bold leading-4 text-slate-900">{{ $appointment->patient->full_name }}</p>
    <p class="truncate text-[11px] leading-4 text-slate-500">{{ $appointment->notes }}</p>
</button>
