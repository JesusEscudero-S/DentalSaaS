{{-- Figma: Citas y agenda / Shell (calendario semanal) --}}
<x-layouts.app title="Agenda" active="agenda" :breadcrumbs="['Agenda' => null]">
    {{-- Encabezado --}}
    <div class="flex items-start justify-between gap-6 pb-5">
        <div class="py-[3px]">
            <h1 class="text-h1 text-slate-900">Agenda</h1>
            <p class="pt-[3px] text-body font-semibold text-slate-400">
                Semana del {{ $weekStart->format('j') }} - {{ $weekEnd->translatedFormat('j \d\e F Y') }}
            </p>
        </div>
        <div class="flex items-center gap-2.5 p-2.5">
            <x-ui.button size="lg" x-data x-on:click="$dispatch('open-modal', { name: 'create-appointment' })">
                <x-ui.icon name="add" :size="16" />
                Nueva cita
            </x-ui.button>
        </div>
    </div>
    
    {{-- Barra: navegación de semanas, vista y filtro por doctor (Figma) --}}
    @php
        $navButton = 'flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50';
        $chip = 'flex h-8 items-center gap-1.5 rounded-full border px-3 text-[12px] font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50';
        $chipOn = 'border-primary-600 bg-primary-50 text-primary-600';
        $chipOff = 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50';
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            {{-- Semana anterior / siguiente: conservan el doctor elegido --}}
            <a href="{{ route('agenda.index', ['semana' => $prevWeek, 'doctor' => $doctorId]) }}" class="{{ $navButton }}"
                aria-label="Semana anterior">
                <x-ui.icon name="expand-right" :size="20" class="rotate-180" />
            </a>

            <p class="min-w-[180px] text-center text-[14px] font-semibold text-slate-900">
                {{ $weekStart->format('j') }} - {{ $weekEnd->translatedFormat('j F Y') }}
            </p>

            <a href="{{ route('agenda.index', ['semana' => $nextWeek, 'doctor' => $doctorId]) }}" class="{{ $navButton }}"
                aria-label="Semana siguiente">
                <x-ui.icon name="expand-right" :size="20" />
            </a>

            {{-- Hoy: sin "semana" en la URL = semana actual --}}
            <a href="{{ route('agenda.index', ['doctor' => $doctorId]) }}"
                class="flex h-8 items-center rounded-lg border border-slate-200 bg-white px-3 text-[12.5px] font-semibold text-slate-900 hover:bg-slate-50">
                Hoy
            </a>

            {{-- Día / Semana / Mes: por ahora solo Semana --}}
            <div class="ml-2 flex rounded-lg bg-slate-100 p-0.5 text-[12.5px] font-semibold">
                <span class="cursor-not-allowed px-3 py-1 text-slate-400" title="Próximamente">Día</span>
                <span class="rounded-md bg-white px-3 py-1 text-slate-900 shadow-sm">Semana</span>
                <span class="cursor-not-allowed px-3 py-1 text-slate-400" title="Próximamente">Mes</span>
            </div>
        </div>

        {{-- Chips de doctor: el punto tiene el mismo color que sus tarjetas --}}
        <nav class="flex flex-wrap items-center gap-2" aria-label="Filtrar por doctor">
            <a href="{{ route('agenda.index', ['semana' => $weekStart->toDateString()]) }}"
                class="{{ $chip }} {{ $doctorId ? $chipOff : $chipOn }}" @if (! $doctorId) aria-current="true" @endif>
                Todos
            </a>

            @foreach ($doctors as $doctor)
                @php
                    // "Dra. Lucía Fonseca" → "Lucía" (como en Figma)
                    $shortName = explode(' ', $doctor->displayName())[1] ?? $doctor->displayName();
                    $isActive = $doctorId === $doctor->id;
                @endphp
                <a href="{{ route('agenda.index', ['semana' => $weekStart->toDateString(), 'doctor' => $doctor->id]) }}"
                    class="{{ $chip }} {{ $isActive ? $chipOn : $chipOff }}" title="{{ $doctor->displayName() }}"
                    @if ($isActive) aria-current="true" @endif>
                    <x-ui.color-dot :color="$doctorColors[$doctor->id]" />
                    {{ $shortName }}
                </a>
            @endforeach
        </nav>
    </div>

    {{-- Calendario semanal --}}
    <x-ui.card class="overflow-hidden">
        <div class="grid grid-cols-[56px_repeat(6,minmax(0,1fr))]">

            {{-- Fila 1: encabezados de los días --}}
            <div class="border-b border-slate-200"></div>
            @foreach ($days as $day)
                <div @class([
                    'border-b border-l border-slate-200 py-3 text-center',
                    'bg-primary-50 text-primary-600' => $day->isToday(),
                ])>
                    {{-- "lun." → "LUN" (Figma no lleva punto) --}}
                    <p class="text-[11px] uppercase">{{ rtrim($day->translatedFormat('D'), '.') }}</p>
                    <p class="text-[18px] font-semibold">{{ $day->day }}</p>
                </div>
            @endforeach

            {{-- Fila 2: columna de horas + una columna por día --}}
            <div>
                @foreach ($hours as $hour)
                    <div class="h-[60px] pr-2 text-right font-mono text-[10px] text-slate-400">{{ $hour }}:00</div>
                @endforeach
            </div>

            @foreach ($days as $day)
                <div class="relative border-l border-slate-200">
                    {{-- líneas de cada hora --}}
                    @foreach ($hours as $hour)
                        <div class="h-[60px] border-b border-slate-100"></div>
                    @endforeach

                    {{-- citas de este día --}}
                    @foreach ($appointmentsByDay[$day->toDateString()] ?? [] as $appointment)
                        <x-agenda.appointment-card :appointment="$appointment" :color="$doctorColors[$appointment->doctor_member_id] ?? 'slate'" />
                    @endforeach
                </div>
            @endforeach
        </div>
    </x-ui.card>

    @push('modals')
        @include('agenda.partials.create-modal')
    @endpush
</x-layouts.app>
