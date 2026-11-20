{{--
    Shell del Expediente clínico (Figma: "Expediente - Contenido"): encabezado del paciente,
    alerta clínica (RN-E3) y pestañas Resumen · Historial · Notas · Archivos · Odontograma.
    Cada pestaña es una página (un enlace), como las vistas Día/Semana/Mes de la Agenda.

    Uso: <x-records.shell :patient="$patient" :record="$record" :doctors="$doctors" active="notas" section="Notas"> … </x-records.shell>
--}}
@props(['patient', 'record', 'doctors', 'active' => 'resumen', 'section' => null])

@php
    $tabs = [
        'resumen' => ['Resumen', route('patients.show', $patient)],
        'historial' => ['Historial', route('records.history', $patient)],
        'notas' => ['Notas', route('records.notes.index', $patient)],
        'archivos' => ['Archivos', route('records.files.index', $patient)],
        'odontograma' => ['Odontograma', route('records.odontogram.index', $patient)],
    ];

    $breadcrumbs = $section
        ? ['Pacientes' => route('patients.index'), $patient->full_name => route('patients.show', $patient), $section => null]
        : ['Pacientes' => route('patients.index'), $patient->full_name => null];
@endphp

<x-layouts.app :title="$patient->full_name" active="pacientes" :breadcrumbs="$breadcrumbs">
    {{-- Cabecera del paciente --}}
    <x-ui.card class="flex items-center justify-between gap-6 px-6 py-5">
        <div class="flex min-w-0 items-center gap-4">
            <x-ui.avatar :initials="$patient->initials" :color="$patient->isActive() ? $patient->avatar_color : 'slate'" size="lg" />
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                    <h1 class="truncate text-[20px] font-semibold leading-7 text-slate-900">{{ $patient->full_name }}</h1>
                    <x-ui.status-badge :status="$patient->status" />
                </div>
                <p class="mt-0.5 text-body-md text-slate-600">
                    {{ $patient->folio }}
                    @if ($patient->age !== null) · {{ $patient->age }} {{ $patient->age === 1 ? 'año' : 'años' }} @endif
                    · {{ $patient->formatted_phone }}
                    @if ($patient->email) · {{ $patient->email }} @endif
                </p>
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-2" x-data>
            @if ($patient->isActive())
                {{-- Los @js van en una etiqueta HTML normal: dentro de un <x-…> rompen el parser de Blade --}}
                <span x-on:click="$dispatch('open-modal', { name: 'deactivate-patient', label: @js($patient->full_name.' ('.$patient->folio.')'), action: @js(route('patients.deactivate', $patient)) })">
                    <x-ui.button variant="ghost-danger">Desactivar</x-ui.button>
                </span>
            @else
                <form method="POST" action="{{ route('patients.reactivate', $patient) }}">
                    @csrf
                    @method('PATCH')
                    <x-ui.button type="submit" variant="secondary">Reactivar</x-ui.button>
                </form>
            @endif
            <span x-on:click="$dispatch('open-modal', { name: 'edit-patient', patient: @js($patient->toFormArray()), action: @js(route('patients.update', $patient)) })">
                <x-ui.button variant="secondary">Editar datos</x-ui.button>
            </span>
        </div>
    </x-ui.card>

    {{-- RN-E3: alerta roja y permanente si hay alergias o antecedentes (se ve en todas las pestañas) --}}
    @if ($record->hasAlerts())
        <div class="mt-4 flex gap-3 rounded-lg border border-danger-500/40 bg-danger-50 px-4 py-3 text-body-md text-danger-700" role="alert">
            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-danger-500 text-[12px] font-bold text-white" aria-hidden="true">!</span>
            <div class="min-w-0">
                <p class="font-semibold">Alerta clínica</p>
                @if (filled($record->allergies))
                    <p><span class="font-semibold">Alergias:</span> {{ $record->allergies }}</p>
                @endif
                @if (filled($record->medical_history))
                    <p><span class="font-semibold">Antecedentes:</span> {{ \Illuminate\Support\Str::limit($record->medical_history, 160) }}</p>
                @endif
            </div>
        </div>
    @endif

    @unless ($patient->isActive())
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-body-md text-amber-800" role="alert">
            Paciente inactivo: no aparece en las búsquedas estándar ni en la Agenda. Su historial se conserva.
        </div>
    @endunless

    {{-- Tabs (Figma: Tab active / inactive) --}}
    <nav class="mt-6 flex gap-1" aria-label="Secciones del expediente">
        @foreach ($tabs as $key => [$label, $href])
            <a href="{{ $href }}" @if ($key === $active) aria-current="page" @endif
                @class([
                    'rounded-lg px-2.5 py-1.5 text-body-md font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
                    'bg-white text-slate-900 shadow-sm' => $key === $active,
                    'text-slate-500 hover:bg-white/60 hover:text-slate-900' => $key !== $active,
                ])>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="mt-4">
        {{ $slot }}
    </div>

    @push('modals')
        @include('patients.partials.modals', ['withCreate' => false, 'doctors' => $doctors])
    @endpush
</x-layouts.app>
